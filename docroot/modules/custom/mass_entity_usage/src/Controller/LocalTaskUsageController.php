<?php

namespace Drupal\mass_entity_usage\Controller;

use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Controller for our pages.
 */
class LocalTaskUsageController extends UsageController {

  /**
   * Lists the usage of a given entity.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   A RouteMatch object.
   *
   * @return array
   *   The page build to be rendered.
   */
  public function listUsageLocalTask(RouteMatchInterface $route_match) {
    $entity = $this->getEntityFromRouteMatch($route_match);
    return $this->listUsagePageSubQuery($entity->getEntityTypeId(), $entity->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleLocalTask(RouteMatchInterface $route_match) {
    return $this->t('Pages linking here');
  }

  /**
   * Checks access based on whether the user can view the current entity.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   A RouteMatch object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccessLocalTask(RouteMatchInterface $route_match) {
    $entity = $this->getEntityFromRouteMatch($route_match);
    return $this->checkAccess($entity->getEntityTypeId(), $entity->id());
  }

}
