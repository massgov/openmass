<?php

namespace Drupal\mass_media\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\mass_media\CacheableBinaryFileResponse;
use Drupal\media\MediaInterface;
use Drupal\stage_file_proxy\DownloadManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class MassMediaRouteController.
 */
class MassMediaDownloadController extends ControllerBase {

  /**
   * Browser and CDN cache lifetime for public document downloads (7 days).
   *
   * Matches the stale-while-revalidate window used by mass_caching and the
   * max-age on the previous 301 redirect to public file URLs.
   */
  private const PUBLIC_FILE_MAX_AGE = 604800;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  private StreamWrapperManagerInterface $streamWrapperManager;

  /**
   * Stage File Proxy download manager.
   *
   * @var \Drupal\stage_file_proxy\DownloadManagerInterface
   */
  private DownloadManagerInterface $stageFileProxyDownloadManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    RequestStack $request_stack,
    StreamWrapperManagerInterface $stream_wrapper_manager,
    DownloadManagerInterface $stage_file_proxy_download_manager,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->requestStack = $request_stack;
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->stageFileProxyDownloadManager = $stage_file_proxy_download_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('stream_wrapper_manager'),
      $container->get('stage_file_proxy.download_manager'),
      $container->get('config.factory')
    );
  }

  /**
   * Serves the file upon request.
   *
   * @param \Drupal\media\MediaInterface $media
   *   A valid media object.
   *
   * @return \Drupal\mass_media\CacheableBinaryFileResponse
   *   File response that serves the media bytes directly.
   *
   * @throws \Exception
   * @throws NotFoundHttpException
   */
  public function download(MediaInterface $media) {
    $bundle = $media->bundle();
    $source = $media->getSource();
    $config = $source->getConfiguration();
    $field = $config['source_field'];

    // This type has no source field configuration.
    if (!$field) {
      throw new \Exception("No source field configured for the {$bundle} media type.");
    }

    $request_query = $this->requestStack->getCurrentRequest()->query;

    // If a delta was provided, use that.
    $delta = $request_query->get('delta');

    // Get the ID of the requested file by its field delta.
    if (is_numeric($delta)) {
      $values = $media->{$field}->getValue();

      if (isset($values[$delta])) {
        $fid = $values[$delta]['target_id'];
      }
      else {
        throw new NotFoundHttpException("The requested file could not be found.");
      }
    }
    else {
      $fid = $media->{$field}->target_id;
    }

    // If media has no file item.
    if (!$fid) {
      throw new NotFoundHttpException("The media item requested has no file referenced/uploaded in the {$field} field.");
    }

    $file = $this->entityTypeManager()->getStorage('file')->load($fid);

    // Or file entity could not be loaded.
    if (!$file) {
      throw new NotFoundHttpException("The requested file id {$fid} could not be found.");
    }

    $file_uri = $file->getFileUri();
    $scheme = $this->streamWrapperManager->getScheme($file_uri);

    if (!$this->fileExists($file_uri)) {
      // Stage File Proxy is intended for public assets; private files should
      // remain non-public and must not be fetched from origin.
      if ($scheme === 'public') {
        $stageConfig = $this->configFactory->get('stage_file_proxy.settings');
        $origin = (string) $stageConfig->get('origin');
        $originHost = (string) parse_url($origin, PHP_URL_HOST);
        $requestHost = $this->requestStack->getCurrentRequest()->getHost();

        // Only fetch when we are not on mass.gov production host.
        if (!empty($originHost) && strcasecmp($requestHost, $originHost) !== 0) {
          $originDir = trim((string) ($stageConfig->get('origin_dir') ?? 'files'));
          $relativePath = str_replace('public://', '', $file_uri);
          $options = ['verify' => (bool) $stageConfig->get('verify')];

          $this->fetchMissingPublicFileFromOrigin($origin, $originDir, $relativePath, $options);
        }
      }
    }

    // Still missing on disk: return 404.
    if (!$this->fileExists($file_uri)) {
      $this->getLogger('mass_media')->notice('Media download file not found for media @mid (file @fid): @uri', [
        '@mid' => $media->id(),
        '@fid' => $file->id(),
        '@uri' => $file_uri,
      ]);
      throw new NotFoundHttpException("The file {$file_uri} does not exist.");
    }

    // Let other modules provide headers and controls access to the file.
    $headers = $this->moduleHandler()->invokeAll('file_download', [$file_uri]);
    foreach ($headers as $result) {
      if ($result == -1) {
        throw new AccessDeniedHttpException();
      }
    }

    $is_public = $scheme !== 'private';
    $response = new CacheableBinaryFileResponse(
      $file_uri,
      Response::HTTP_OK,
      $headers,
      $is_public,
      NULL,
      TRUE,
      TRUE,
    );

    if (empty($headers['Content-Disposition'])) {
      $mime_type = $file->getMimeType() ?: 'application/octet-stream';
      $disposition = $this->resolveContentDisposition($mime_type, $request_query);
      $response->setContentDisposition($disposition, $file->getFilename());
    }

    if (!$response->headers->has('Content-Type')) {
      $response->headers->set('Content-Type', $file->getMimeType() ?: 'application/octet-stream');
    }

    $this->configureResponseCache($response, $is_public);
    $response->addCacheableDependency($media);
    $response->addCacheableDependency($file);
    $response->getCacheableMetadata()->addCacheContexts(['url.site']);
    // Prevent Dynamic Page Cache from serializing the BinaryFileResponse object.
    // HTTP cache headers (max-age/s-maxage) and Akamai Edge-Cache-Tag headers are
    // set separately for CDN caching and invalidation.
    $response->getCacheableMetadata()->setCacheMaxAge(0);

    return $response;
  }

  /**
   * Applies HTTP cache headers so Akamai can cache public document downloads.
   */
  private function configureResponseCache(BinaryFileResponse $response, bool $is_public): void {
    if (!$is_public) {
      $response->setPrivate();
      $response->headers->addCacheControlDirective('no-store');
      return;
    }

    $response->setMaxAge(self::PUBLIC_FILE_MAX_AGE);
    $response->setSharedMaxAge(self::PUBLIC_FILE_MAX_AGE);
  }

  /**
   * Attempts to fetch a missing public file from the Stage File Proxy origin.
   *
   * Failures are logged and swallowed so the caller can return a 404 instead
   * of an uncaught 500 from non-Guzzle errors in the fetch path.
   */
  private function fetchMissingPublicFileFromOrigin(
    string $origin,
    string $origin_dir,
    string $relative_path,
    array $options,
  ): void {
    try {
      $fetched = $this->stageFileProxyDownloadManager->fetch(
        $origin,
        $origin_dir,
        $relative_path,
        $options
      );
      if (!$fetched) {
        $this->getLogger('mass_media')->warning('Stage File Proxy could not fetch @path from @origin', [
          '@path' => $relative_path,
          '@origin' => $origin,
        ]);
      }
    }
    catch (\Throwable $e) {
      $this->getLogger('mass_media')->error('Stage File Proxy fetch error for @path: @msg', [
        '@path' => $relative_path,
        '@msg' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Determines whether a file exists at the given stream-wrapper URI.
   */
  private function fileExists(string $file_uri): bool {
    return is_file($file_uri);
  }

  /**
   * Resolves the Content-Disposition header for a download response.
   */
  private function resolveContentDisposition(string $mime_type, $request_query): string {
    if ($request_query->has('attachment')) {
      return ResponseHeaderBag::DISPOSITION_ATTACHMENT;
    }
    if ($request_query->has(ResponseHeaderBag::DISPOSITION_INLINE)) {
      return ResponseHeaderBag::DISPOSITION_INLINE;
    }
    if ($this->isBrowserViewableMimeType($mime_type)) {
      return ResponseHeaderBag::DISPOSITION_INLINE;
    }

    return ResponseHeaderBag::DISPOSITION_ATTACHMENT;
  }

  /**
   * Whether a MIME type should be displayed inline in the browser by default.
   */
  private function isBrowserViewableMimeType(string $mime_type): bool {
    if ($mime_type === 'application/pdf' || $mime_type === 'text/plain') {
      return TRUE;
    }

    return str_starts_with($mime_type, 'image/');
  }

}
