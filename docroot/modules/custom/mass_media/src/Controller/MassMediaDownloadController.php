<?php

namespace Drupal\mass_media\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class MassMediaRouteController.
 */
class MassMediaDownloadController extends ControllerBase {

  /**
   * Renderer service object.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  private $renderer;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * {@inheritdoc}
   */
  public function __construct(RequestStack $request_stack, Renderer $renderer) {
    $this->requestStack = $request_stack;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('renderer')
    );
  }

  /**
   * Serves the file upon request.
   *
   * @param \Drupal\media\MediaInterface $media
   *   A valid media object.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   TrustedRedirectResponse object.
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

    // If a delta was provided, use that.
    $delta = $this->requestStack->getCurrentRequest()->query->get('delta');

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

    // Catches stray metadata not handled properly by file_create_url().
    // @see https://www.drupal.org/project/drupal/issues/2867355
    $context = new RenderContext();
    $uri = $this->renderer->executeInRenderContext($context, function () use ($uri) {
      return \Drupal::service('file_url_generator')->generateAbsoluteString($uri);
    });

    // Returns a 301 Moved Permanently redirect response.
    $response = new TrustedRedirectResponse($uri, 301);
    // Adds cache metadata.
    $response->getCacheableMetadata()->addCacheContexts(['url.site']);
    $response->addCacheableDependency($media);
    $response->addCacheableDependency($file);
    if (!$context->isEmpty()) {
      $response->addCacheableDependency($context->pop());
    }
    return $response;
  }

}
