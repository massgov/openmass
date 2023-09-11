<?php

namespace Drupal\mass_migrate\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\mass_migrate\MassMigrateBatchManager;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mass Migrate drush commands.
 */
class MassMigrateCommands extends DrushCommands {

  /**
   * The Mass Migrate batch manager.
   *
   * @var \Drupal\mass_migrate\MassMigrateBatchManager
   */
  protected $batchManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(MassMigrateBatchManager $batch_manager) {
    parent::__construct();
    $this->batchManager = $batch_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mass_migrate.batch_manager'),
    );
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

  /**
   * Create missing flagging.
   *
   * @command mass-migrate:queue-flagging
   */
  public function queueFlagging(): void {
    $this->batchManager->queueFlagging();
    drush_backend_batch_process();
  }

}
