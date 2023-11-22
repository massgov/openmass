<?php

namespace Drupal\mass_serializer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mass_serializer\CacheEndpoint;
use Drupal\mass_serializer\RenderEndpoint;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class DataJsonController.
 */
class DataJsonController extends ControllerBase {

  /**
   * The service that renders views endpoints.
   *
   * @var Drupal\mass_serializer\RenderEndpoint
   */
  protected $renderer;

  /**
   * The service that caches and retrieves views endpoints.
   *
   * @var Drupal\mass_serializer\CacheEndpoint
   */
  protected $cache;

  protected $api = 'documents_by_filter';
  protected $display = 'rest_export_documents_by_contributor';

  /**
   * Constructs a new DataJsonController object.
   */
  public function __construct(RenderEndpoint $mass_serializer_render_endpoint, CacheEndpoint $mass_serializer_cache_endpoint) {
    $this->renderer = $mass_serializer_render_endpoint;
    $this->cache = $mass_serializer_cache_endpoint;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mass_serializer.render_endpoint'),
      $container->get('mass_serializer.cache_endpoint')
    );
  }

  /**
   * Output Endpoint.
   *
   * @param Drupal\taxonomy\TermInterface $organization
   *   Term ID of this organization was passed in the url.
   *
   * @return string
   *   Return Hello string.
   */
  public function endpoint(TermInterface $organization) {
    $args = [$organization->id()];
    $response = new Response();

    $response->headers->set('Content-Type', 'application/json');

    if ($this->cache->cacheExists($this->api, $args)) {
      $filename = $this->cache->cacheName($this->api, $args);
      $response->setExpires(new \DateTime('+ 24 hours'));
      $response->setContent(file_get_contents($filename));
    }
    else {
      $output = $this->renderer->render($this->api, $this->display, $args);

      if (!empty($output)) {
        $response->setContent($output);
      }
      else {
        $response->setStatusCode(404);
        $response->setContent(json_encode('ID not found.'));
      }
    }

    $response->sendHeaders();

    return $response;
  }

}
