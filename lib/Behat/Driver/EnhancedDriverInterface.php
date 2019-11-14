<?php

namespace MassGov\Behat\Driver;

use Drupal\Driver\DriverInterface;

interface EnhancedDriverInterface extends DriverInterface {

  /**
   * Retrieve a one-time login link for a user.
   *
   * @param \stdClass $account
   *   The user account object.
   *
   * @return string
   *   The one-time login link.
   */
  public function getLoginLink(\stdClass $account);
}
