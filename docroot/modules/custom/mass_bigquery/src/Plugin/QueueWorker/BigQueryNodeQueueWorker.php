<?php

namespace Drupal\mass_bigquery\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Defines 'mass_bigquery_node_queue' queue worker.
 *
 * @QueueWorker(
 *   id = "mass_bigquery_node_queue",
 *   title = @Translation("BigQuery node queue"),
 *   cron = {"time" = 60}
 * )
 */
class BigQueryNodeQueueWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // @todo Process data here.
  }

}
