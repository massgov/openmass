<?php

namespace Drupal\mass_admin_pages\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('system.admin')) {
      $route->setDefault('_controller', '\Drupal\mass_admin_pages\Controller\AuthorHomePageController::authorHome');
    }
  }

}
