<?php

namespace Drupal\mass_search\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trait for mass_search controller implementations.
 */
trait CorsResponseTrait {

  /**
   * Adds a CORS header for the request origin if it is allowed.
   *
   * Also updates the passed cache metadata with the origin context.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache_metadata
   *   The cache metadata for the current request.
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   The outgoing response that might need a CORS header.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The currently active request object.
   */
  public function addCorsHeaderToResponse(CacheableMetadata $cache_metadata, Response $response, Request $request) {
    $origin = $request->headers->get('Origin');
    $cache_metadata->addCacheContexts(['headers:Origin']);
    $response->setVary('Origin');

    // Web and host.docker.internal are for backstop.
    $allowed_origins = [
      'localhost',
      'host.docker.internal',
      'web',
      'search.mass.gov',
      'mass.local',
      'search.digital.mass.gov',
      'devsearch.digital.mass.gov',
      'stagesearch.digital.mass.gov',
    ];

    if (!$origin) {
      return;
    }

    $origin_parts = parse_url($origin);
    if (!$origin_parts || empty($origin_parts['scheme']) || empty($origin_parts['host'])) {
      return;
    }

    $scheme = strtolower($origin_parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], TRUE)) {
      return;
    }

    $host = strtolower($origin_parts['host']);
    if (in_array($host, $allowed_origins, TRUE)) {
      $response->headers->set('Access-Control-Allow-Origin', $origin);
    }
  }

}
