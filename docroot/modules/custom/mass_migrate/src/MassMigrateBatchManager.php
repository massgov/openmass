<?php

namespace Drupal\mass_migrate;

use Drupal\Core\Utility\UpdateException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Manages Mass Auto Parents batch processing.
 */
class MassMigrateBatchManager implements ContainerInjectionInterface {

  /**
   * The size of the batch.
   */
  const BATCH_SIZE = 50;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $nodeStorage;

  /**
   * Creates a MassAutoParentsBatchManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Queue parents relationships automatically using a custom table.
   */
  public function queueFlagging() {
    $batch = $this->generateBatch();
    batch_set($batch);
  }

  /**
   * Create a batch to process the custom table relationships.
   *
   * @return array
   *   The batch array.
   */
  public function generateBatch() {
    $operations = [];

    // Read the CSV file into an array
    $module_path = \Drupal::service('file_system')->realpath(\Drupal::service('module_handler')->getModule('mass_migrate')->getPath());
    $csvFile = $module_path . '/includes/exported_data.csv'; // Replace with your CSV file name

    $csvData = [];

    if (($handle = fopen($csvFile, 'r')) !== false) {
      while (($row = fgetcsv($handle)) !== false) {
        $csvData[] = $row;
      }
      fclose($handle);
    }

    $total = (int) count($csvData);

    $batched_results = array_chunk($csvData, self::BATCH_SIZE, TRUE);
    $progress_count = 0;
    foreach ($batched_results as $batch_group) {
      $progress_count += (int) count($batch_group);
      $operations[] = ['\Drupal\mass_migrate\MassMigrateBatchManager::queueParentsBatchWorker', [$batch_group, $progress_count, $total]];
    }

    $batch = [
      'operations' => $operations,
      'finished' => '\Drupal\mass_migrate\MassMigrateBatchManager::batchFinished',
      'title' => 'Queueing relationships from table.',
      'progress_message' => 'Processed @current of @total relationships.',
      'error_message' => 'This batch encountered an error.',
    ];

    return $batch;
  }

  /**
   * Batch operation worker for queueing up parent relationship assignments.
   *
   * @param array $batch_group
   *   Array of relationships to assign.
   * @param int $progress_count
   *   Progress count of relationships.
   * @param int $total
   *   Total count of relationships.
   * @param mixed $context
   *   Batch context.
   */
  public static function queueParentsBatchWorker(array $batch_group, $progress_count, $total, &$context) {
    $queue = \Drupal::queue('mass_migrate_queue');
    if (empty($context['sandbox']['total'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['total'] = (int) count($batch_group);
    }
    foreach ($batch_group as $row) {
      if ($row[0] == 'entity_id' || $row[0] == 66641) {
        continue;
      }
      $entityId = $row[0]; // Change the index to match your CSV columns
      $uid = $row[1]; // Change the index to match your CSV columns
      // Add to queue.
      $queue->createItem([
        'entity_id' => $entityId,
        'uid' => $uid,
      ]);
      $context['sandbox']['progress']++;
      $context['results'][] = $entityId . ':' . $uid;
    }

    if ($context['sandbox']['progress'] < $context['sandbox']['total']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['total'];
    }
    else {
      $context['finished'] = 1;
    }

    $context['message'] = t('Queueing parent relationships: @current of @total', [
      '@current' => $progress_count,
      '@total' => $total,
    ]);
  }

  /**
   * Finish callback for our batch processing.
   *
   * @param bool $success
   *   Whether the batch completed successfully.
   * @param array $results
   *   The results array.
   * @param array $operations
   *   The operations array.
   */
  public static function batchFinished($success, array $results, array $operations) {
    if ($success) {
      \Drupal::messenger()->addMessage(t('Queued parent relationship assignments for @count nodes.', ['@count' => count($results)]));
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      \Drupal::messenger()->addMessage(
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
