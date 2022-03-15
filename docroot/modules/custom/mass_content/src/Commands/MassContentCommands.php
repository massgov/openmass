<?php

namespace Drupal\mass_content\Commands;

use Drupal\mass_content\MassContentBatchManager;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mass Content drush commands.
 */
class MassContentCommands extends DrushCommands {

  /**
   * The Mass Auto Parents batch manager.
   *
   * @var \Drupal\mass_auto_parents\MassAutoParentsBatchManager
   */
  protected $batchManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(MassContentBatchManager $batch_manager) {
    parent::__construct();
    $this->batchManager = $batch_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mass_content.batch_manager'),
    );
  }

  /**
   * Assign parents automatically based on custom table values.
   *
   * @command mass-content:migrate-dates
   */
  public function migrateDateFields() {
    $this->batchManager->migrateDateFields();
    drush_backend_batch_process();
  }

}
