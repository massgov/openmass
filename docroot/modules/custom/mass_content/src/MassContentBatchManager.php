<?php

namespace Drupal\mass_content;

use Drupal\Core\Entity\ContentEntityBase;

/**
 * Manages Mass Content batch processing.
 */
class MassContentBatchManager {

//  /**
//   * The size of the batch.
//   */
//  const BATCH_SIZE = 50;
//
//  /**
//   * The entity type manager.
//   *
//   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
//   */
//  protected $nodeStorage;
//
//  /**
//   * Creates a MassContentBatchManager object.
//   *
//   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
//   *   The entity type manager service.
//   */
//  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
//    $this->nodeStorage = $entity_type_manager->getStorage('node');
//  }
//
//  /**
//   * {@inheritdoc}
//   */
//  public static function create(ContainerInterface $container) {
//    return new static(
//      $container->get('entity_type.manager'),
//    );
//  }
//
//  /**
//   * Migrate date field values with batch process.
//   */
//  public function migrateDateFields() {
//    $batch = $this->generateBatch();
//    batch_set($batch);
//  }
//
//  /**
//   * Create a batch to process.
//   *
//   * @return array
//   *   The batch array.
//   */
//  public function generateBatch() {
//    $operations = [];
//
//    $query = \Drupal::entityQuery('node');
//    $query->condition('type', ['advisory', 'binder', 'decision', 'executive_order', 'info_details', 'regulation', 'rules', 'news'], 'IN');
//    $count = clone $query;
//    $results = $query->execute();
//    $total = (int) $count->count()->execute();
//    if ($total == 0) {
//      throw new UpdateException('Nothing to migrate.');
//    }
//    $batched_results = array_chunk($results, self::BATCH_SIZE, TRUE);
//    $progress_count = 0;
//    foreach ($batched_results as $batch_group) {
//      $progress_count += (int) count($batch_group);
//      $batch_group = $this->nodeStorage->loadMultiple($batch_group);
//      $operations[] = ['\Drupal\mass_content\MassContentBatchManager::migrateDateFieldsBatchWorker', [$batch_group, $progress_count, $total]];
//    }
//
//    $batch = [
//      'operations' => $operations,
//      'finished' => '\Drupal\mass_content\MassContentBatchManager::batchFinished',
//      'title' => 'Queueing nodes for field data migration.',
//      'progress_message' => 'Processed @current of @total relationships.',
//      'error_message' => 'This batch encountered an error.',
//    ];
//
//    return $batch;
//  }
//
//  /**
//   * Batch operation for processing the nodes.
//   *
//   * @param array $batch_group
//   *   Array of relationships to assign.
//   * @param int $progress_count
//   *   Progress count of relationships.
//   * @param int $total
//   *   Total count of relationships.
//   * @param mixed $context
//   *   Batch context.
//   */
//  public static function migrateDateFieldsBatchWorker(array $batch_group, $progress_count, $total, &$context) {
//    // Don't spam all the users with content update emails.
//    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
//
//    // Turn off entity_hierarchy writes while processing the item.
//    \Drupal::state()->set('entity_hierarchy_disable_writes', TRUE);
//
//    $memory_cache = \Drupal::service('entity.memory_cache');
//
//    // Sets a mapping of content type to "date" fields.
//    $date_fields = [
//      'binder' => 'field_binder_date_published',
//      'decision' => 'field_decision_date',
//      'executive_order' => 'field_executive_order_date',
//      'info_details' => 'field_info_details_date_publishe',
//      'regulation' => 'field_regulation_last_updated',
//      'rules' => 'field_rules_effective_date',
//      'advisory' => 'field_advisory_date',
//      'news' => 'field_news_date'
//    ];
//
//    if (empty($context['sandbox']['total'])) {
//      $context['sandbox']['progress'] = 0;
//      $context['sandbox']['total'] = (int) count($batch_group);
//    }
//
//    foreach ($batch_group as $node) {
//
//      try {
//        // Set the updated date for events.
//        if (in_array($node->bundle(), array_keys($date_fields))) {
//          $field_name = $date_fields[$node->bundle()];
//          if ($node->hasField($field_name) && $node->hasField('field_date_published')) {
//            if (!$node->$field_name->isEmpty()) {
//
//              $published_date = $node->get($field_name)->getValue();
//              if ($field_name == 'field_news_date') {
//                $news_date = $node->get($field_name)->getValue()[0]['value'];
//                $published_date = explode('T', $news_date)[0];
//              }
//              $node->set($field_name, NULL);
//              $node->set('field_date_published', $published_date);
//              // Save the node.
//              // Save without updating the last modified date. This requires a core patch
//              // from the issue: https://www.drupal.org/project/drupal/issues/2329253.
//              $node->setSyncing(TRUE);
//              $node->save();
//            }
//          }
//        }
//      }
//      catch (\Exception $e) {
//        \Drupal::messenger()->addMessage(t('An error occurred with message: @error', ['@error' => $e->getMessage()]));
//        \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
//      }
//
//      $context['sandbox']['progress']++;
//
//      $context['results'][] = Markup::create('Migrated date field value for the node ' . $node->id() . ':' . $node->bundle());
//    }
//
//    if ($context['sandbox']['progress'] < $context['sandbox']['total']) {
//      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['total'];
//    }
//    else {
//      $context['finished'] = 1;
//    }
//
//    $context['message'] = t('Processing nodes to update date fields: @current of @total', [
//      '@current' => $progress_count,
//      '@total' => $total,
//    ]);
//
//    $memory_cache->deleteAll();
//    // Turn on entity_hierarchy writes after processing the item.
//    \Drupal::state()->set('entity_hierarchy_disable_writes', FALSE);
//  }
//
//  /**
//   * Finish callback for our batch processing.
//   *
//   * @param bool $success
//   *   Whether the batch completed successfully.
//   * @param array $results
//   *   The results array.
//   * @param array $operations
//   *   The operations array.
//   */
//  public static function batchFinished($success, array $results, array $operations) {
//    if ($success) {
//      \Drupal::messenger()->addMessage(t('Processed date field update for the @count nodes.', ['@count' => count($results)]));
//    }
//    else {
//      // An error occurred.
//      // $operations contains the operations that remained unprocessed.
//      $error_operation = reset($operations);
//      \Drupal::messenger()->addMessage(
//        t('An error occurred while processing @operation with arguments : @args',
//          [
//            '@operation' => $error_operation[0],
//            '@args' => print_r($error_operation[0], TRUE),
//          ]
//        )
//      );
//    }
//  }

  public function processNode($id, ContentEntityBase $node, $operation_details, &$context) {
    // Sets a mapping of content type to "date" fields.
    $date_fields = [
      'binder' => 'field_binder_date_published',
      'decision' => 'field_decision_date',
      'executive_order' => 'field_executive_order_date',
      'info_details' => 'field_info_details_date_publishe',
      'regulation' => 'field_regulation_last_updated',
      'rules' => 'field_rules_effective_date',
      'advisory' => 'field_advisory_date',
      'news' => 'field_news_date'
    ];

    if (in_array($node->bundle(), array_keys($date_fields))) {
      $field_name = $date_fields[$node->bundle()];
      if ($node->hasField($field_name) && $node->hasField('field_date_published')) {
        if (!$node->$field_name->isEmpty()) {

          $published_date = $node->get($field_name)->getValue();
          if ($field_name == 'field_news_date') {
            $news_date = $node->get($field_name)->getValue()[0]['value'];
            $published_date = explode('T', $news_date)[0];
          }
          $node->set($field_name, NULL);
          $node->set('field_date_published', $published_date);
          // Save the node.
          // Save without updating the last modified date. This requires a core patch
          // from the issue: https://www.drupal.org/project/drupal/issues/2329253.
          $node->setSyncing(TRUE);
          $node->save();
        }
      }
    }

    // Store some results for post-processing in the 'finished' callback.
    // The contents of 'results' will be available as $results in the
    // 'finished' function (in this example, batch_example_finished()).
    $context['results'][] = $id;

    // Optional message displayed under the progressbar.
    $context['message'] = t('Running Batch "@id" @details',
      ['@id' => $id, '@details' => $operation_details]
    );
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
  public function processNodeFinished($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      // Here we could do something meaningful with the results.
      // We just display the number of nodes we processed...
      $messenger->addMessage(t('@count results processed.', ['@count' => count($results)]));
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $messenger->addMessage(
        t('An error occurred while processing @operation with arguments : @args',
          [
            '@operation' => $error_operation[0],
            '@args' => print_r($error_operation[0], TRUE),
          ]
        )
      );
    }
  }
}
