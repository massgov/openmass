<?php

namespace MassGov\Behat\Driver\Cores;

use Drupal\Driver\Cores\CoreInterface;

interface EnhancedCoreInterface extends CoreInterface {

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
