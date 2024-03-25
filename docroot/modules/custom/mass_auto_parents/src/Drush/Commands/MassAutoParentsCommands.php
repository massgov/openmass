<?php

namespace Drupal\mass_auto_parents\Drush\Commands;

use Drupal\mass_auto_parents\MassAutoParentsBatchManager;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mass Auto Parents drush commands.
 */
class MassAutoParentsCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(protected MassAutoParentsBatchManager $batchManager) {
    parent::__construct();
  }

  /**
   * Assign parents automatically based on custom table values.
   *
   * @command mass-auto-parents:queue-parent-assignment
   */
  public function queueParentAssignment() {
    $this->batchManager->queueParentAssignment();
    drush_backend_batch_process();
  }

}
