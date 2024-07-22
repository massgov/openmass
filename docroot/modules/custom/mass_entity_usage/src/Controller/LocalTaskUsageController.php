<?php

namespace Drupal\mass_entity_usage\Controller;

use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Controller for our pages.
 */
class LocalTaskUsageController extends ListUsageController {

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

  /**
   * Retrieves entity from route match.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity object as determined from the passed-in route match.
   */
  protected function getEntityFromRouteMatch(RouteMatchInterface $route_match) {
    $parameter_name = $route_match->getRouteObject()->getOption('_entity_usage_entity_type_id');
    return $route_match->getParameter($parameter_name);
  }

}
