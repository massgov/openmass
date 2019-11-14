<?php

namespace Drupal\mass_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class DefaultController.
 *
 * @package Drupal\mass_search\Controller
 */
class DefaultController extends ControllerBase {

  /**
   * Redirects Search queries to new site.
   */
  public function search(Request $request) {
    $url = Url::fromUri('https://search.mass.gov', [
      'query' => ['q' => $request->query->get('q')],
    ]);
    $response = new TrustedRedirectResponse($url->toString(), 301);
    // Vary the page cache based on the "q" URL parameter.
    $response->getCacheableMetadata()
      ->addCacheContexts(['url.query_args:q']);

    return $response;
  }

}
