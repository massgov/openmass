<?php

namespace MassGov\Behat\Driver;

use Drupal\Driver\DrupalDriver;
use MassGov\Behat\Driver\Cores\EnhancedCoreInterface;

class EnhancedDriver extends DrupalDriver implements EnhancedDriverInterface {

  /**
   * {@inheritdoc}
   */
  public function getLoginLink(\stdClass $account) {
    if(!$this->core instanceof EnhancedCoreInterface) {
      throw new \Exception('Drupal core was not an instance of `EnhancedCoreInterface`');
    }
    return $this->core->getLoginLink($account);
  }
}
