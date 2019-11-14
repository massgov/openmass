<?php

namespace Drupal\mass_hardening\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class RouteSubscriber.
 *
 * Listens to dynamic route events.
 *
 * @package Drupal\mass_hardening\Routing
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Add a custom access check to the user reset route.
    // Hash check on the user.reset.login route is now handled by prlp module.
    foreach (['user.reset', 'user.reset.login'] as $route_name) {
      $route = $collection->get($route_name);
      if ($route) {
        $route->addRequirements(['_mass_hardening_hash_check' => '\Drupal\mass_hardening\Access\MassHardeningAccess::access']);
      }
    }
    // Restrict filter/tips/{filter} pages to logged in users.
    foreach (['filter.tips'] as $route_name) {
      $route = $collection->get($route_name);
      if ($route) {
        // Doing this carefully in case some other module has added other requirements.
        $requirements = $route->getRequirements();
        unset($requirements['_access']);
        $requirements['_user_is_logged_in'] = 'TRUE';
        $route->setRequirements($requirements);
      }
    }

  }

}
