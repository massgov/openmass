<?php

namespace Drupal\mass_hierarchy;

use Drupal\Core\Routing\CurrentRouteMatch;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Custom RouteMatch class which includes setter method.
 */
class MassHierarchyRouteMatch extends CurrentRouteMatch {

  /**
   * Constructs a CurrentRouteMatch object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(RequestStack $request_stack) {
    parent::__construct($request_stack);
  }

  /**
   * Getter method to get the parameter we want.
   */
  public function getParameter($parameter_name) {
    return $this->parameters[$parameter_name] ?? NULL;
  }

  /**
   * Setter method to add the parameter we want.
   */
  public function setParameter($name, $value) {
    return $this->parameters[$name] = $value;
  }

}
