<?php

namespace Drupal\mass_entity_usage\Routing;

use Drupal\entity_usage\Routing\RouteSubscriber;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Registers a route for generic usage local tasks for entities.
 */
class MassRouteSubscriber extends RouteSubscriber {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $configured_types = $this->config->get('entity_usage.settings')->get('local_task_enabled_entity_types') ?: [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      // We prefer the canonical template, but we also allow edit-form templates
      // on entities that don't have canonical (like views, etc).
      if ($entity_type->hasLinkTemplate('canonical')) {
        $template = $entity_type->getLinkTemplate('canonical');
      }
      elseif ($entity_type->hasLinkTemplate('edit-form')) {
        $template = $entity_type->getLinkTemplate('edit-form');
      }
      if (empty($template) || !in_array($entity_type_id, $configured_types, TRUE)) {
        continue;
      }
      $options = [
        '_admin_route' => TRUE,
        '_entity_usage_entity_type_id' => $entity_type_id,
        'parameters' => [
          $entity_type_id => [
            'type' => 'entity:' . $entity_type_id,
          ],
        ],
      ];

      $route = new Route(
        $template . '/mass-usage',
        [
          '_controller' => '\Drupal\mass_entity_usage\Controller\MassLocalTaskUsageController::listUsageLocalTask',
          '_title_callback' => '\Drupal\mass_entity_usage\Controller\MassLocalTaskUsageController::getTitleLocalTask',
        ],
        [
          '_permission' => 'access entity usage statistics',
          '_custom_access' => '\Drupal\mass_entity_usage\Controller\MassLocalTaskUsageController::checkAccessLocalTask',
        ],
        $options
      );

      $collection->add("entity.$entity_type_id.entity_usage", $route);
    }
  }

}
