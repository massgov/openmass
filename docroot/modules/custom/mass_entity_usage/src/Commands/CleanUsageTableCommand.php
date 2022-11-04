<?php

namespace Drupal\mass_entity_usage\Commands;

use Drush\Commands\DrushCommands;

/**
 * Exposes the CleanUsageTable service to clean the usage table.
 */
class CleanUsageTableCommand extends DrushCommands {

  private $cleanUsageTableService;

  /**
   * {@inheritdoc}
   */
  public function __construct($clean_usage_table) {
    $this->cleanUsageTableService = $clean_usage_table;
  }

  /**
   * Drush command that clean the usage table.
   *
   * @command clean_usage_table
   */
  public function clean() {
    $this->cleanUsageTableService->clean();
  }

}
