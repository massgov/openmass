<?php

namespace Drupal\mass_media\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\media\MediaInterface;
use Drupal\stage_file_proxy\DownloadManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class MassMediaRouteController.
 */
class MassMediaDownloadController extends ControllerBase {

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
   * Drupal filesystem service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private FileSystemInterface $fileSystem;

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
    FileSystemInterface $file_system,
    DownloadManagerInterface $stage_file_proxy_download_manager,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->requestStack = $request_stack;
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->fileSystem = $file_system;
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
      $container->get('file_system'),
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
   * @return \Drupal\mass_media\Response\CacheableBinaryFileResponse
   *   File response that supports Drupal cache metadata.
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

    $uri = $file->getFileUri();
    $scheme = $this->streamWrapperManager->getScheme($uri);

    // If the file doesn't exist locally on non-production environments,
    // try fetching it from the configured Stage File Proxy origin.
    if (!$this->streamWrapperManager->isValidScheme($scheme)) {
      throw new NotFoundHttpException("The file {$uri} does not exist.");
    }

    if (!file_exists($uri)) {
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
          $relativePath = str_replace('public://', '', $uri);
          $options = ['verify' => (bool) $stageConfig->get('verify')];

          $this->stageFileProxyDownloadManager->fetch(
            $origin,
            $originDir,
            $relativePath,
            $options
          );
        }
      }
    }

    // Still missing on disk: return 404.
    if (!file_exists($uri)) {
      throw new NotFoundHttpException("The file {$uri} does not exist.");
    }

    // Let other modules provide headers and controls access to the file.
    $headers = $this->moduleHandler()->invokeAll('file_download', [$uri]);
    foreach ($headers as $result) {
      if ($result == -1) {
        throw new AccessDeniedHttpException();
      }
    }

    $response = new BinaryFileResponse($uri, Response::HTTP_OK, $headers, $scheme !== 'private');

    if (empty($headers['Content-Disposition'])) {
      if ($request_query->has(ResponseHeaderBag::DISPOSITION_INLINE)) {
        $disposition = ResponseHeaderBag::DISPOSITION_INLINE;
      }
      else {
        $disposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT;
      }
      $response->setContentDisposition($disposition, $file->getFilename());
    }

    if (!$response->headers->has('Content-Type')) {
      $response->headers->set('Content-Type', $file->getMimeType() ?: 'application/octet-stream');
    }

    return $response;
  }

}
