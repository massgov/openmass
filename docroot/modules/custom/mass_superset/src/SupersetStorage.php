<?php

declare(strict_types=1);

namespace Drupal\mass_superset;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Site\Settings;

/**
 * Storage class for Superset data.
 *
 * This is used to retrieve and update local storage of data that is housed
 * in the ETL database used by Superset.
 */
class SupersetStorage implements SupersetStorageInterface {

  /**
   * The table name to store superset data in.
   */
  const TABLE = 'mass_superset_data';

  /**
   * Connection to the Drupal Database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The table name as a class property.
   *
   * @var string
   */
  private $table;

  /**
   * The connection to Superset.
   *
   * @var \Drupal\mass_superset\SupersetDatabaseClient
   */
  protected $supersetDatabaseClient;

  /**
   * Used to retrieve configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The Drupal queue for processing data.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Drupal\Core\Entity\Query\QueryFactory definition.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * Array of required read-only configuration Superset connection.
   *
   * @var array
   */
  protected $settings;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(Connection $database, SupersetDatabaseClient $superset_database_client, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, QueueFactory $queue, Settings $settings, LoggerChannelFactoryInterface $logger_factory, $table = self::TABLE) {
    $this->database = $database;
    $this->supersetDatabaseClient = $superset_database_client;
    $this->table = $table;
    $this->config = $config_factory->get('mass_superset.config');
    $this->entityQuery = $entity_type_manager->getStorage('node')->getQuery();
    $this->queue = $queue->get('mass_superset_data_queue');
    $this->settings = $settings->get('mass_superset');
    $this->logger = $logger_factory->get('mass_superset');
  }

  /**
   * {@inheritdoc}
   */
  public function queueAll(): void {
    $types = $this->config->get('types');
    $batch = $this->config->get('batch');
    $start = 0;

    do {
      $query = $this->entityQuery
        ->condition('type', $types, 'IN')
        ->range($start, $batch);
      $ids = $query->execute();
      if (!empty($ids)) {
        $this->queue->createItem(['ids' => $ids]);
      }
      $start += $batch;
    } while (!empty($ids));
  }

  /**
   * {@inheritdoc}
   */
  public function updateRecords(array $ids): bool {

    if (empty($this->settings['SUPERSET_URL']) || empty($this->settings['SUPERSET_USERNAME']) || empty($this->settings['SUPERSET_PASSWORD'])) {
      $this->logger->warning('Could not find settings for Superset connection.');
      return FALSE;
    }
    // Delete any previously existing records for these IDs.
    $this->database->delete($this->table)->condition('nid', $ids, 'IN')->execute();
    $time = \Drupal::time()->getRequestTime();
    // Fetch data from Superset.
    $query =
        'SELECT * FROM analytics.pageviews_scores_1_month WHERE node_id IN(' . implode(', ', $ids) . ')';
    $options = [
      'base_uri' => $this->settings['SUPERSET_URL'],
      'username' => $this->settings['SUPERSET_USERNAME'],
      'password' => $this->settings['SUPERSET_PASSWORD'],
      'database_id' => $this->settings['SUPERSET_DATABASE_ID'],
      'schema' => 'analytics',
    ];
    $stats = $this->supersetDatabaseClient->runQuery($query, $options);
    foreach ($stats['data'] as $stat) {
      $this->database->merge($this->table)
        ->key('nid', $stat['node_id'])
        ->fields([
          'nid' => $stat['node_id'],
          'pageviews' => $stat['pageviews'],
          'score' => $stat['gpa_score'],
          'last_updated' => $time,
          'nos_per_1000' => $stat['nos_per_1000'],
          'eject_rate' => $stat['eject_rate'],
          'broken_links' => $stat['broken_links'],
          'grade_level' => $stat['grade_level'],
          'total_no' => $stat['total_no'],
          'total_yes' => $stat['total_yes'],
          'total_feedback' => $stat['total_feedback'],
        ])
        ->execute();
    }
    return TRUE;
  }

}
