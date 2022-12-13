<?php

namespace Drupal\mass_active_directory\Routing;

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
    if ($route = $collection->get('user.login')) {
      $route->setDefault('_title', 'Login to edit Mass.gov');
    }
  }

}
