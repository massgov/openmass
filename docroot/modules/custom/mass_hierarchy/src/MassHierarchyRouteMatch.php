<?php

namespace Drupal\mass_hierarchy;

use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Custom RouteMatch class which includes setter method.
 */
class MassHierarchyRouteMatch implements RouteMatchInterface {

  /**
   * {@inheritdoc}
   */
  protected array $parameters;

  /**
   * {@inheritdoc}
   */
  public function getRouteName(): string {
    return 'entity.node.canonical';
  }

  /**
   * {@inheritdoc}
   */
  public function getRawParameter($parameter_name) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getParameters() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRawParameters() {
    return $this->parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteObject() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getParameter($name) {
    return $this->parameters[$name] ?? null;
  }

  /**
   * Setter method to add the parameter we want.
   */
  public function setParameter($name, $value) {
    return $this->parameters[$name] = $value;
  }
}
