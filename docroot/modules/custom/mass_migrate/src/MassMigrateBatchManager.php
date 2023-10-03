<?php

namespace Drupal\mass_migrate;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manages Mass Migrate batch processing.
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
   * Creates a MassMigrateBatchManager object.
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
   * Queue flags.
   */
  public function queueFlagging() {
    $batch = $this->generateBatch();
    batch_set($batch);
  }

  /**
   * Create a batch to process the flagging.
   *
   * @return array
   *   The batch array.
   */
  public function generateBatch() {
    $operations = [];

    // Read the CSV file into an array
    $module_path = \Drupal::service('file_system')->realpath(\Drupal::service('module_handler')->getModule('mass_migrate')->getPath());
    $csvFile = $module_path . '/includes/exported_data.csv';

    $csvData = [];

    if (($handle = fopen($csvFile, 'r')) !== FALSE) {
      while (($row = fgetcsv($handle)) !== FALSE) {
        $csvData[] = $row;
      }
      fclose($handle);
    }

    $total = (int) count($csvData);

    $batched_results = array_chunk($csvData, self::BATCH_SIZE, TRUE);
    $progress_count = 0;
    foreach ($batched_results as $batch_group) {
      $progress_count += (int) count($batch_group);
      $operations[] = ['\Drupal\mass_migrate\MassMigrateBatchManager::queueFlagBatchWorker', [$batch_group, $progress_count, $total]];
    }

    $batch = [
      'operations' => $operations,
      'finished' => '\Drupal\mass_migrate\MassMigrateBatchManager::batchFinished',
      'title' => 'Queueing flagging.',
      'progress_message' => 'Processed @current of @total flags.',
      'error_message' => 'This batch encountered an error.',
    ];

    return $batch;
  }

  /**
   * Batch operation worker for queueing flagging.
   *
   * @param array $batch_group
   *   Array of flagging to assign.
   * @param int $progress_count
   *   Progress count of flagging.
   * @param int $total
   *   Total count of flagging.
   * @param mixed $context
   *   Batch context.
   */
  public static function queueFlagBatchWorker(array $batch_group, $progress_count, $total, &$context) {
    $queue = \Drupal::queue('mass_migrate_queue');
    if (empty($context['sandbox']['total'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['total'] = (int) count($batch_group);
    }
    foreach ($batch_group as $row) {
      // Ignore problematic node with id: 66641 and the first row of csv file.
      if ($row[0] == 'entity_id' || $row[0] == 66641) {
        continue;
      }
      $entityId = $row[0];
      $uid = $row[1];
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

    $context['message'] = t('Queueing flags: @current of @total', [
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
      \Drupal::messenger()->addMessage(t('Queued flagging assignments for @count nodes.', ['@count' => count($results)]));
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
