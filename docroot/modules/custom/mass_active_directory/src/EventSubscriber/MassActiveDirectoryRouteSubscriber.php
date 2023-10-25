<?php

namespace Drupal\mass_active_directory\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class MassActiveDirectoryRouteSubscriber.
 *
 * Listens to the dynamic route events.
 */
class MassActiveDirectoryRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $routes = ['user.pass', 'user.pass.http', 'user.login.http', 'user.reset.form', ''];
    foreach ($routes as $route_id) {
      if ($route = $collection->get($route_id)) {
        $route->setRequirement('_access', 'FALSE');
      }
    }

    if ($route = $collection->get('user.login')) {
      $route->setDefault('_title', 'Log in to edit Mass.gov');
    }
  }

}
