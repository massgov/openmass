<?php

namespace Drupal\mass_utility\Logger;

use Monolog\Handler\NewRelicHandler;
use Monolog\LogRecord;

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
  public function write(LogRecord $record): void {
    // Graceful failure if New Relic isn't enabled. On Acquia (legacy), NR is not enabled
    // in Drush contexts (can be dynamically loaded), so this prevents the exception that would otherwise
    // be thrown.
    if (!$this->isNewRelicEnabled()) {
      return;
    }

    parent::write($record);
  }

}
