<?php

declare(strict_types=1);

namespace Drupal\mass_bigquery;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Site\Settings;

/**
 * Storage class for Bigquery data.
 *
 * This is used to retrieve and update local storage of data that is housed
 * in the ETL database used by Bigquery.
 */
class BigqueryStorage implements BigqueryStorageInterface {

  /**
   * The table name to store bigquery data in.
   */
  const TABLE = 'mass_bigquery_data';

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
   * The connection to Bigquery.
   *
   * @var \Drupal\mass_bigquery\BigqueryClient
   */
  protected $bigqueryClient;

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
   * Array of required read-only configuration Bigquery connection.
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
  public function __construct(Connection $database, BigqueryClient $bigquery_client, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, QueueFactory $queue, Settings $settings, LoggerChannelFactoryInterface $logger_factory, $table = self::TABLE) {
    $this->database = $database;
    $this->bigqueryClient = $bigquery_client;
    $this->table = $table;
    $this->config = $config_factory->get('mass_bigquery.config');
    $this->entityQuery = $entity_type_manager->getStorage('node')->getQuery();
    $this->queue = $queue->get('mass_bigquery_node_queue');
    $this->settings = $settings->get('mass_bigquery');
    $this->logger = $logger_factory->get('mass_bigquery');
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
        ->condition('type', $types, 'IN')->accessCheck(FALSE)
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
    if (getenv('GOOGLE_APPLICATION_CREDENTIALS') === FALSE) {
      $this->logger->warning('Could not find credentials for BigQuery connection.');
      return FALSE;
    }
    // Delete any previously existing records for these IDs.
    $this->database->delete($this->table)->condition('nid', $ids, 'IN')->execute();
    $time = \Drupal::time()->getRequestTime();
    // Fetch data from Bigquery.
    $query =
        'SELECT nodeId, totalPageViews, nosPerKUniquePageViews, ejectRate, negativeSurveys, positiveSurveys, brokenLinks, gradeLevel FROM `MassgovGA4_testing.aggregated_node_analytics` WHERE nodeId IN(' . implode(', ', $ids) . ')';
    $queryResults = $this->bigqueryClient->runQuery($query);
    foreach ($queryResults as $row) {
      $nos_per_1000 = $row['nosPerKUniquePageViews'];
      $pageviews = $row['totalPageViews'];
      $total_no = $row['negativeSurveys'] ?? 0;
      $this->database->merge($this->table)
        ->key('nid', $row['nodeId'])
        ->fields([
          'nid' => $row['nodeId'],
          'pageviews' => $pageviews,
          'last_updated' => $time,
          'nos_per_1000' => $nos_per_1000,
          'eject_rate' => $row['ejectRate'],
          'total_no' => $total_no,
          'total_yes' => $row['positiveSurveys'],
          'total_feedback' => $row['negativeSurveys'] + $row['positiveSurveys'],
          'broken_links' => $row['brokenLinks'],
          'grade_level' => $row['gradeLevel'],
          'nos_per_1000_cleaned' => $this->calculateNosPer1000Cleaned($nos_per_1000, $pageviews, $total_no),
        ])
        ->execute();
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecords(array $ids): array {

    if (getenv('GOOGLE_APPLICATION_CREDENTIALS') === FALSE) {
      $this->logger->warning('Could not find credentials for BigQuery connection.');
      return [];
    }
    // Delete any previously existing records for these IDs.
    $this->database->delete($this->table)->condition('nid', $ids, 'IN')->execute();
    $time = \Drupal::time()->getRequestTime();
    // Fetch data from Bigquery.
    $query =
      'SELECT nodeId, totalPageViews, nosPerKUniquePageViews, ejectRate, negativeSurveys, positiveSurveys, brokenLinks, gradeLevel FROM `MassgovGA4_testing.aggregated_node_analytics` WHERE nodeId IN(' . implode(', ', $ids) . ')';
    $queryResults = $this->bigqueryClient->runQuery($query);
    $result = [];
    foreach ($queryResults as $row) {
      $result[$row['nodeId']] = $row;
    }
    return $result;
  }

  /**
   * Set if there are 500 or more pageviews or 5 or more nos/negative responses.
   */
  private function calculateNosPer1000Cleaned(float $nos_per_1000, int $pageviews, int $total_no): ?float {
    if (($pageviews >= 500) || $total_no >= 5) {
      return $nos_per_1000;
    }
    return NULL;
  }

}
