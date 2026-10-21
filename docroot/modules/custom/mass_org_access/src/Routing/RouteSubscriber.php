<?php

namespace Drupal\mass_org_access\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tightens write-action routes that bypass the standard node edit flow.
 *
 * Routes such as the children-reorder, move-children, and redirects forms
 * only require `node.view` access by default, so the org gate in
 * hook_node_access() never gets consulted. We swap the requirement to
 * `node.update`, which routes the access decision through the same
 * mass_org_access checks editors hit on the canonical edit form.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * Route names whose write operations must respect org access.
   */
  private const PROTECTED_ROUTES = [
    'entity.node.entity_hierarchy_reorder',
    'view.change_parents.page_1',
    'entity.node.redirects',
  ];

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach (self::PROTECTED_ROUTES as $route_name) {
      $route = $collection->get($route_name);
      if (!$route) {
        continue;
      }
      $route->setRequirement('_entity_access', 'node.update');
    }
  }

}
