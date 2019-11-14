<?php

namespace Drupal\mass_fields\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Override the handler for the entity autocomplete route.
 *
 * @package Drupal\mass_fields\Routing
 */
class AutocompleteRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('system.entity_autocomplete')) {
      $route->setDefault('_controller', '\Drupal\mass_fields\Controller\EntityAutocompleteController::handleAutocomplete');
    }
  }

}
