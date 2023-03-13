<?php

namespace Drupal\mass_migrate\Commands;

use Drush\Commands\DrushCommands;

/**
 * Mass Migrate drush commands.
 */
class MassMigrateCommands extends DrushCommands {

  /**
   * Override config when running drush migrate:import.
   *
   * @hook pre-command migrate:import
   */
  public function overrideMigrateImportPreCommand() {
    // Usage updates now gfo into a queue which we will process at end of migration.
    $GLOBALS['config']['entity_usage.settings']['track_enabled_source_entity_types'] = ['placeholder', 'another'];
    \Drupal::service('config.factory')->clearStaticCache();
  }

}
