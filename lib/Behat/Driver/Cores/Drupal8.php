<?php


namespace MassGov\Behat\Driver\Cores;

use Drupal\Driver\Cores\Drupal8 as Base;
use Drupal\user\Entity\User;

/**
 * Extended Drupal 8 Core.
 *
 * To add a custom method:
 *
 * 1. Implement it on EnhancedDriver and EnhancedDriverInterface.
 * 2. Implement it on Drupal8 and EnhancedCoreInterface.
 * 3. When invoking one of our custom methods, ensure that you are dealing with
 * an EnhancedDriverInterface.
 */
class Drupal8 extends Base implements EnhancedCoreInterface {

  /**
   * {@inheritdoc}
   */
  public function getLoginLink(\stdClass $account) {
    return user_pass_reset_url(User::load($account->uid)). '/login';
  }
}
