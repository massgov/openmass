<?php

namespace Drupal\mass_superset\Plugin\QueueWorker;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\mass_superset\SupersetStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process Superset queue items.
 *
 * @QueueWorker(
 *   id = "mass_superset_data_queue",
 *   title = @Translation("Superset node data queue processing"),
 *   cron = {"time" = 300}
 * )
 */
class SupersetDataQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The current active database's master connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  protected $supersetStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database, SupersetStorageInterface $superset_storage) {
    $this->database = $database;
    $this->supersetStorage = $superset_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('superset.storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (!empty($data['ids'])) {
      return $this->supersetStorage->updateRecords($data['ids']);
    }
  }

}
