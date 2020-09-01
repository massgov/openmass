<?php

namespace Drupal\mass_utility\Logger;

use Monolog\Handler\NewRelicHandler;

/**
 * New Relic logging handler.
 *
 * Falls back gracefully if the New Relic PHP extension isn't enabled.
 * This was necessary because the newrelic extension isn't loaded in
 * the CLI.
 */
class AcquiaNewRelicHandler extends NewRelicHandler {

  /**
   * {@inheritdoc}
   */
  public function write(array $record): void {
    // Graceful failure if New Relic isn't enabled. On Acquia, NR is not enabled
    // in Drush contexts, so this prevents the exception that would otherwise
    // be thrown.
    if (!$this->isNewRelicEnabled()) {
      return;
    }

    parent::write($record);
  }

}
