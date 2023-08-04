<?php

namespace Drupal\mass_migrate\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;

/**
 * Mass Migrate drush commands.
 */
class MassMigrateCommands extends DrushCommands {

  /**
   * Override config when running drush entity:delete.
   *
   * @hook pre-command entity:delete
   */
  public function overrideEntityDeletePreCommand(CommandData $commandData) {
    if (isset($commandData->getArgsAndOptions()['entity_type']) && isset($commandData->getArgsAndOptions()['options']['bundle'])) {
      if ($commandData->getArgsAndOptions()['entity_type'] == 'node' && $commandData->getArgsAndOptions()['options']['bundle'] == 'service_details') {
        // Set variables to process entity_hierarchy items with a queue
        \Drupal::state()->set('entity_hierarchy_disable_writes', TRUE);
      }
    }
  }

  /**
   * Override config when running drush entity:delete.
   *
   * @hook post-command entity:delete
   */
  public function overrideEntityDeletePostCommand() {
    // Unset variables after running the command.
    \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
  }

  /**
   * Override config when running drush migrate:import.
   *
   * @hook pre-command migrate:import
   */
  public function overrideMigratImportPreCommand(CommandData $commandData) {
    // Turn off entity_hierarchy writes before processing the items.
    \Drupal::state()->set('entity_hierarchy_disable_writes', TRUE);
    \Drupal::service('config.factory')->clearStaticCache();
    \Drupal::service('cache.discovery')->deleteAll();
  }

  /**
   * Override config after running drush migrate:import.
   *
   * @hook post-command migrate:import
   */
  public function overrideMigrateImportPostCommand() {
    // Usage updates now go into a queue which we will process at end of migration.
    $GLOBALS['config']['entity_usage.settings']['track_enabled_source_entity_types'] = ['placeholder', 'another'];

    // Disable entity_hierarchy writes for service_details migration.
    \Drupal::state()->set('mass_migrate_service_details', FALSE);

    // Turn on entity_hierarchy writes after processing the items.
    \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
    \Drupal::service('cache.discovery')->deleteAll();
  }

}
