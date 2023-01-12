<?php

namespace Drupal\mass_views;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

class NodeArgumentAccessHandler {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $currentRouteMatch;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * Constructs NodeArgumentAccessHandler.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   */
  public function __construct(AccountInterface $account, RouteMatchInterface $current_route_match) {
    $this->account = $account;
    $this->currentRouteMatch = $current_route_match;
  }

  /**
   * Check if user has access to the entity.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   Returns the access result.
   */
  public function access(AccountInterface $account) {
    $node = $this->currentRouteMatch->getParameter('node');
    if ($node->access('view', $account)) {
      return AccessResult::allowed()->addCacheableDependency($node);
    }
    return AccessResult::forbidden()->addCacheableDependency($node);
  }

}
