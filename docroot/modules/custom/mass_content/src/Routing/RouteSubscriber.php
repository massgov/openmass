<?php

namespace Drupal\mass_content\Routing;

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
    if ($route = $collection->get('view.change_parents.page_1')) {
      // Entity access check expects the parameter to be upconverted.
      $options = $route->getOptions();
      $options['parameters']['node']['type'] = 'entity:node';
      $route->setOptions($options);
      $route->setRequirement('_access', 'FALSE');
    }

    if ($route = $collection->get('entity.node.entity_hierarchy_reorder')) {
      $route->setRequirement('_access', 'FALSE');
    }
  }

}
