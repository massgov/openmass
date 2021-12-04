<?php

namespace Drupal\mass_content_api\Routing;

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
    if ($route = $collection->get('mass_content_api.descendant_controller_linking_page')) {
      // Entity access check expects the parameter to be upconverted.
      $options = $route->getOptions();
      $options['parameters']['node']['type'] = 'entity:node';
      $route->setOptions($options);
    }
    if ($route = $collection->get('mass_content_api.descendant_controller_media_linking_page')) {
      // Entity access check expects the parameter to be upconverted.
      $options = $route->getOptions();
      $options['parameters']['media']['type'] = 'entity:media';
      $route->setOptions($options);
    }
  }

}
