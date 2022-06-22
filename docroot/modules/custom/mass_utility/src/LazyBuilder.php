<?php

namespace Drupal\mass_utility;

use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Contains callbacks that can be used in #lazy_builder callbacks.
 */
class LazyBuilder {

  /**
   * Constructor.
   */
  public function __construct(RouteMatchInterface $routeMatch) {
    $this->routeMatch = $routeMatch;
  }

  /**
   * Return the Node ID for the node that's currently being viewed.
   *
   * IMPORTANT: this functionality is used in the feedback form. Contact the
   * data team before making changes here.
   */
  public function currentNid() {
    $nid = $this->routeMatch->getRawParameter('node');

    // For location listing pages, set the nid to 0 to prevent Formstack from
    // mixing the results with the location page.
    $route_name = $this->routeMatch->getRouteName();
    if ($route_name === 'mass_map.map_page') {
      $nid = 0;
    }

    return [
      '#cache' => [
        'contexts' => ['route'],
      ],
      '#markup' => $nid ?: 0,
    ];
  }

}
