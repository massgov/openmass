<?php

namespace Drupal\mass_auto_parents\Commands;

use Drupal\mass_auto_parents\MassAutoParentsBatchManager;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mass Auto Parents drush commands.
 */
class MassAutoParentsCommands extends DrushCommands {

  /**
   * The Mass Auto Parents batch manager.
   *
   * @var \Drupal\mass_auto_parents\MassAutoParentsBatchManager
   */
  protected $batchManager;


  /**
   * {@inheritdoc}
   */
  public function __construct(MassAutoParentsBatchManager $batch_manager) {
    parent::__construct();
    $this->batchManager = $batch_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mass_auto_parents.batch_manager'),
    );
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
