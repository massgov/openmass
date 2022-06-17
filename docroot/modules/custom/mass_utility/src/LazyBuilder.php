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

    return [
      '#cache' => [
        'contexts' => ['route'],
      ],
      '#markup' => $nid ?: 0,
    ];
  }

}
