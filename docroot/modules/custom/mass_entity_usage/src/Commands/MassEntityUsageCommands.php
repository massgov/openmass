<?php

namespace Drupal\mass_entity_usage\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mass_entity_usage\EntityUsageQueueBatchManager;
use Drush\Commands\DrushCommands;

/**
 * Mass Entity usage drush commands.
 */
class MassEntityUsageCommands extends DrushCommands {

  /**
   * The Entity Usage queue batch manager.
   *
   * @var \Drupal\mass_entity_usage\EntityUsageQueueBatchManager
   */
  protected $queueBatchManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity usage configuration.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $entityUsageConfig;

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityUsageQueueBatchManager $queue_batch_manager, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, $database) {
    parent::__construct();
    $this->queueBatchManager = $queue_batch_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityUsageConfig = $config_factory->get('entity_usage.settings');
    $this->database = $database;
  }

  /**
   * Recreate all entity usage statistics.
   *
   * @command mass-content:usage-regenerate
   * @aliases maur,mass-usage-regenerate
   * @option batch-size
   *   The --batch-size flag can be optionally used to
   *   specify the batch size, for example --batch-size=500.
   */
  public function recreate($options = ['batch-size' => 1000]) {
    $this->database->delete('queue_unique')->condition('name', 'entity_usage_tracker')->execute();
    $this->queueBatchManager->populateQueue($options['batch-size']);
    drush_backend_batch_process();
  }

}
