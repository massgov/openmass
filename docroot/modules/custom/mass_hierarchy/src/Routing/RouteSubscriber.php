<?php

namespace Drupal\mass_hierarchy\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.node.entity_hierarchy_reorder')) {
      $route->setDefault('_title', 'Hierarchy');
      $route->setDefault('title', 'Hierarchy');
    }
  }

}
