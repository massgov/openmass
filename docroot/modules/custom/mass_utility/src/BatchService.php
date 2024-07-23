<?php

namespace Drupal\mass_utility;

use DrushBatchContext;

/**
 * Class BatchService.
 *
 * This class is used to provide additional handling for batch jobs run by
 * Drush commands.
 */
class BatchService {

  /**
   * Batch process callback for the revisions cleanup drush command.
   *
   * @param array $nids
   *   The nids to use when finding revisions.
   * @param int $timestamp
   *   The timestamp to use when pruning revisions.
   * @param int $batch
   *   The number of revisions to queue at a time.
   * @param array $context
   *   Context for operations.
   */
  public static function populateRevisionsCleanupQueue(array $nids, $timestamp, $batch, array &$context) {
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
    }

    // Instantiate the revisions queue.
    $queue = \Drupal::queue('mass_utility_revisions_cleanup');
    $queue->createQueue();

    // Setup the required variables for the while loop.
    $rev_finished = FALSE;
    $rev_offset = 0;
    while (!$rev_finished) {
      // Load all of the revisions for the given set of nodes that were
      // "changed" before the provided timestamp. This "changed" value works
      // as "created" value for revisions since revisions are never truly
      // edited. Never include the currently active revision (r.vid <> n.vid).
      $vid_query = \Drupal::database()
        ->select('node_field_revision', 'r')
        ->fields('r', ['vid']);
      $vid_query->join('node', 'n', 'n.nid = r.nid');
      $vid_query->condition('r.nid', $nids, 'IN')
        ->where('r.vid <> n.vid');
      if ($timestamp) {
        $vid_query->condition('r.changed', $timestamp, '<');
      }
      $vid_query->orderBy('r.vid', 'ASC')
        ->range($rev_offset, $batch);

      $revision_ids = $vid_query->execute()->fetchCol();
      $rev_offset += $batch;

      // Add the set of revisions to the queue for later processing during cron
      // runs and/or when directly invoked via the drush "queue-run" command.
      if (!empty($revision_ids)) {
        $queue->createItem([
          'rids' => $revision_ids,
        ]);
        $context['sandbox']['progress']++;
        $context['results'][] = count($revision_ids);
      }

      // Stop the loop when all revisions have been queued.
      $rev_finished = count($revision_ids) < $batch;

    }

  }

  /**
   * Batch Finished callback.
   *
   * @param bool $success
   *   Success of the operation.
   * @param array $results
   *   Array of results for post processing.
   * @param array $operations
   *   Array of operations.
   */
  public static function populateRevisionsCleanupQueueFinished($success, array $results, array $operations) {
    $messenger = \Drupal::service('messenger');
    if ($success) {
      $messenger->addMessage(
        t('Finished queuing @count revisions for clean up.',
          [
            '@count' => array_sum($results),
          ]
        )
      );
    }
    else {
      $messenger->addMessage(t('Finished with errors.'));
    }

  }

}
