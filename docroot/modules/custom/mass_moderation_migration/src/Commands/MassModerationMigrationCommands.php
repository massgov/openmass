<?php

namespace Drupal\mass_moderation_migration\Commands;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\mass_moderation_migration\MigrationController;
use Drush\Commands\DrushCommands;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate_tools\MigrateExecutable;

/**
 * Drush commands for running mass_moderation_migration.
 */
class MassModerationMigrationCommands extends DrushCommands {

  /**
   * The migration controller service.
   *
   * @var \Drupal\mass_moderation_migration\MigrationController
   */
  protected $controller;

  /**
   * The module installer service.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected $moduleInstaller;

  /**
   * MassModerationMigrationCommands constructor.
   *
   * @param \Drupal\mass_moderation_migration\MigrationController $controller
   *   The migration controller service.
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $module_installer
   *   The module installer service.
   */
  public function __construct(MigrationController $controller, ModuleInstallerInterface $module_installer) {
    $this->controller = $controller;
    $this->moduleInstaller = $module_installer;
  }

  /**
   * Saves moderation state data to temporary migration tables.
   *
   * @command mmm:save
   * @aliases mmm-save
   */
  public function save() {
    $out = $this->output();
    $out->writeln('Saving existing moderation states to temporary tables...');
    $messages = $this->controller->executeStepWithMessages('save');
    array_walk($messages, [$out, 'writeln']);
  }

  /**
   * Deletes moderation state data.
   *
   * @param bool $standalone
   *   Internal use only. TRUE if the command is being run directly.
   *
   * @command mmm:clear
   * @aliases mmm-clear
   */
  public function clear($standalone = TRUE) {
    $out = $this->output();
    $out->writeln('Removing Workbench Moderation data...');
    $messages = $this->controller->executeStepWithMessages('clear');
    array_walk($messages, [$out, 'writeln']);
    if ($standalone) {
      $out->writeln('You should now be able to uninstall Workbench Moderation and install Content Moderation.');
    }
  }

  /**
   * Restores moderation state data from temporary migration tables.
   *
   * @command mmm:restore
   * @aliases mmm-restore
   */
  public function restore() {
    $out = $this->output();
    $mh = \Drupal::moduleHandler();

    if ($mh->moduleExists('workbench_moderation')) {
      $out->writeln('Please uninstall Workbench Moderation before continuing.');
      return;
    }

    if (!$mh->moduleExists('content_moderation')) {
      $out->writeln('Please install Content Moderation before continuing.');
      return;
    }
    elseif (!$mh->moduleExists('workflows')) {
      $out->writeln('Please install Workflows before continuing.');
      return;
    }

    $messages = new MigrateMessage();
    $migration = \Drupal::service('plugin.manager.migration')->createInstance('mass_moderation_migration_restore', []);
    $executable = new MigrateExecutable($migration, $messages, ['feedback' => 5000]);

    $executable->import();
  }

}
