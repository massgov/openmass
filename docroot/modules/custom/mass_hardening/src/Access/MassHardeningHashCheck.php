<?php

namespace Drupal\mass_hardening\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatch;

/**
 * Class MassHardeningHashCheck.
 *
 * Note that this implements a deprecated interface.
 *
 * @see https://www.drupal.org/project/drupal/issues/2266817#comment-12345693.
 * @package Drupal\mass_hardening\Access
 */
class MassHardeningHashCheck implements AccessInterface {

  private \Drupal\Core\Entity\EntityStorageInterface $userStorage;

  /**
   * Constructs a MassHardeningAccess object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   EntityTypeManager is needed so that we can load a user.
   *
   * @internal param \Drupal\user\UserStorageInterface $user_storage The user storage.
   *   The user storage.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager) {
    $this->userStorage = $entity_manager->getStorage('user');
  }

  /**
   * Access control for routes that provide uid/timestamp/hash values.
   *
   * Denies access unless values are present, user is active and hash validates.
   *
   * @param \Drupal\Core\Routing\RouteMatch $route
   *   The RouteMatch object provides access to the route parameters.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   Returns forbidden unless uid, timestamp and hash validate.
   */
  public function access(RouteMatch $route) {
    $uid = $route->getParameter('uid');
    $timestamp = $route->getParameter('timestamp');
    $hash = $route->getParameter('hash');

    // Start with a positive access check which is cacheable for the current
    // route, which includes both route name and parameters.
    $access = AccessResult::allowed();
    $access->addCacheContexts(['route']);

    // Suspenders and belt.
    if (!$uid || !$timestamp || !$hash) {
      return $access->andIf(AccessResult::forbidden('Invalid parameters.'));
    }

    // The Mass.gov access denied page does not currently reproduce this message.
    // This message is not translated because forbidden() expects a string.
    $message = 'You have tried to use a one-time login link that has either been used or is not valid.';

    /** @var \Drupal\user\UserInterface $user */
    $user = $this->userStorage->load($uid);
    // Since we're about to check the status of the user, and later the hash
    // we now need to vary the cache based on the user object.
    $access->addCacheableDependency($user);
    if ($user === NULL || !$user->isActive()) {
      // Blocked or invalid user ID, so deny access. The parameters will be in
      // the watchdog's URL for the administrator to check.
      return $access->andIf(AccessResult::forbidden($message));
    }

    // At the point where we actually test the hash, prevent any caching.
    $access->setCacheMaxAge(0);
    if (!hash_equals($hash, user_pass_rehash($user, $timestamp))) {
      // Invalid hash, so deny access.
      return $access->andIf(AccessResult::forbidden($message));
    }

    return $access;
  }

}
