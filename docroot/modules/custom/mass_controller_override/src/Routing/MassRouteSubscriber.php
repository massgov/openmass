<?php

namespace Drupal\mass_controller_override\Routing;

/**
 * @file
 * Contains Drupal\mass_controller_override\Routing\MassRouteSubscriber.
 */

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class MassRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    // Change controller class for "node/add".
    $route = $collection->get('node.add_page');
    if (!empty($route)) {
      $route->setDefaults([
        '_title' => 'Add content',
        '_controller' => '\Drupal\mass_controller_override\Controller\MassControllerOverrideNodeController::addPage',
      ]);
    }
  }

}
