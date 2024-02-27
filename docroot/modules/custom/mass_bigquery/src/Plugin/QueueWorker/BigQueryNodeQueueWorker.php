<?php

namespace Drupal\mass_bigquery\Plugin\QueueWorker;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\mass_bigquery\BigqueryStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'mass_bigquery_node_queue' queue worker.
 *
 * @QueueWorker(
 *   id = "mass_bigquery_node_queue",
 *   title = @Translation("BigQuery node queue"),
 * )
 */
class BigQueryNodeQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The current active database's master connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  protected $bigqueryStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database, BigqueryStorageInterface $bigquery_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
    $this->bigqueryStorage = $bigquery_storage;
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
      $container->get('bigquery.storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (!empty($data['ids'])) {
      return $this->bigqueryStorage->updateRecords($data['ids']);
    }
  }

}
