<?php

namespace Drupal\mass_entity_usage;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manages Entity Usage integration with Batch API specifically for the queue.
 *
 * This is different than EntityUsageBatchManager in that here we want to have
 * statistics regenerated in background through a standard Drupal queue, but in
 * order to create items for the queue, we will use a batch process, to avoid
 * timeouts and memory issues.
 */
class EntityUsageQueueBatchManager implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The default size of the batch for the revision queries.
   */
  const BATCH_SIZE = 100;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity usage configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Creates a EntityUsageQueueBatchManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TranslationInterface $string_translation, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $string_translation;
    $this->config = $config_factory->get('entity_usage.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('string_translation'),
      $container->get('config.factory')
    );
  }

  /**
   * Populate the queue to recreate the entity usage statistics.
   *
   * Generate a batch to queue and recreate the statistics for all entities.
   *
   * @param int $batch_size
   *   (Optional) The batch size to use when executing the batch process to
   *   populate the queue. Defaults to static::BATCH_SIZE.
   */
  public function populateQueue($batch_size = 0) {
    $batch = $this->generateBatch($batch_size);
    batch_set($batch);
  }

  /**
   * Create a batch to queue the entity types in bulk.
   *
   * @param int $batch_size
   *   (Optional) The batch size to use when executing the batch process to
   *   populate the queue. Defaults to static::BATCH_SIZE.
   *
   * @return array{operations: array<array{callable-string, array}>, finished: callable-string, title: \Drupal\Core\StringTranslation\TranslatableMarkup, progress_message: \Drupal\Core\StringTranslation\TranslatableMarkup, error_message: \Drupal\Core\StringTranslation\TranslatableMarkup}
   *   The batch array.
   */
  public function generateBatch($batch_size = 0) {
    $batch_size = (int) $batch_size > 0 ? (int) $batch_size : static::BATCH_SIZE;
    $operations = [];
    $to_track = $this->config->get('track_enabled_source_entity_types');
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      // Only look for entities enabled for tracking on the settings form.
      $track_this_entity_type = FALSE;
      if (!is_array($to_track) && ($entity_type->entityClassImplements('\Drupal\Core\Entity\ContentEntityInterface'))) {
        // When no settings are defined, track all content entities by default,
        // except for Files and Users.
        if (!in_array($entity_type_id, ['file', 'user'])) {
          $track_this_entity_type = TRUE;
        }
      }
      elseif (is_array($to_track) && in_array($entity_type_id, $to_track, TRUE)) {
        $track_this_entity_type = TRUE;
      }
      if ($track_this_entity_type) {
        $operations[] = [
          '\Drupal\mass_entity_usage\EntityUsageQueueBatchManager::queueSourcesBatchWorker',
          [$entity_type_id, $batch_size],
        ];
      }
    }

    $batch = [
      'operations' => $operations,
      'finished' => '\Drupal\mass_entity_usage\EntityUsageQueueBatchManager::batchFinished',
      'title' => $this->t('Populating queue to recreate entity usage statistics.'),
      'progress_message' => $this->t('Queued @current of @total entity types.'),
      'error_message' => $this->t('This batch encountered an error.'),
    ];

    return $batch;
  }

  /**
   * Batch operation worker for populating the queue to regenerate statistics.
   *
   * @param string $entity_type_id
   *   The entity type id, for example 'node'.
   * @param int $batch_size
   *   The batch size.
   * @param array{sandbox: array{progress?: int, total?: int, current_item?: int}, results: string[], finished: int, message: string} $context
   *   Batch context. May be an array, or implementing \ArrayObject in the case
   *   of Drush.
   */
  public static function queueSourcesBatchWorker($entity_type_id, $batch_size, &$context) {
    $queue = \Drupal::queue('entity_usage_tracker');

    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $entity_type_key = $entity_type->getKey('id');

    // First pass, populate the sandbox.
    if (empty($context['sandbox']['total'])) {
      // Log the start of the batch for this entity type.
      \Drupal::logger('entity_usage.batch')->info('Starting batch process for entity type: @entity_type.', ['@entity_type' => $entity_type_id]);

      // Delete current usage statistics for these entities.
      \Drupal::service('entity_usage.usage')
        ->bulkDeleteSources($entity_type_id);

      $context['sandbox']['progress'] = 0;
      // Set the total to the number of entities.
      $context['sandbox']['total'] = (int) $entity_storage->getQuery()
        ->accessCheck(FALSE)
        ->count()
        ->execute();
      $context['sandbox']['current_item'] = 0;

      // Log the total number of entities found.
      \Drupal::logger('entity_usage.batch')->info('Total entities to process for @entity_type: @total', [
        '@entity_type' => $entity_type_id,
        '@total' => $context['sandbox']['total'],
      ]);

      $context['finished'] = 0;
    }

    if ($context['sandbox']['total'] > 0) {
      try {
        // Query entities in batches.
        $current_id = $context['sandbox']['current_item'];
        $result = $entity_storage->getQuery()
          ->condition($entity_type_key, $current_id, '>')
          ->accessCheck(FALSE)
          ->sort($entity_type_key)
          ->range(0, $batch_size)
          ->execute();
        $entity_ids = array_values($result);

        foreach ($entity_ids as $entity_id) {
          $queue->createItem([
            'operation' => 'insert',
            'entity_type' => $entity_type_id,
            'entity_id' => $entity_id,
          ]);
          $context['sandbox']['current_item'] = $entity_id;
          $context['sandbox']['progress']++;
        }

        // Log progress after every 100 entities processed.
        if ($context['sandbox']['progress'] % 100 === 0) {
          \Drupal::logger('entity_usage.batch')->info('Processed @progress of @total entities for @entity_type', [
            '@progress' => $context['sandbox']['progress'],
            '@total' => $context['sandbox']['total'],
            '@entity_type' => $entity_type_id,
          ]);
        }
      }
      catch (\Exception $e) {
        \Drupal::logger('entity_usage.batch')->error('Exception while processing @entity_type: @message', [
          '@entity_type' => $entity_type_id,
          '@message' => $e->getMessage(),
        ]);
      }

      $context['results'][] = $entity_type_id;
    }

    if ($context['sandbox']['progress'] < $context['sandbox']['total']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['total'];
    }
    else {
      $context['finished'] = 1;
      // Log the successful completion of the entity type.
      \Drupal::logger('entity_usage.batch')->info('Completed batch process for entity type: @entity_type.', ['@entity_type' => $entity_type_id]);
    }

    $context['message'] = t('Populating entity usage queue for entity type @entity_type: @current of @total', [
      '@entity_type' => $entity_type_id,
      '@current' => $context['sandbox']['progress'],
      '@total' => $context['sandbox']['total'],
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
      $types = array_unique($results);
      \Drupal::messenger()->addStatus(t('Created queue items to regenerate entity usage statistics for entity types: @types.', [
        '@types' => implode(", ", $types),
      ]));
      \Drupal::logger('entity_usage.batch')->info('Batch completed successfully for entity types: @types.', [
        '@types' => implode(", ", $types),
      ]);
    }
    else {
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      \Drupal::messenger()->addMessage(
        t('An error occurred while processing @operation with arguments : @args', [
          '@operation' => $error_operation[0],
          '@args' => print_r($error_operation[0], TRUE),
        ])
      );
      \Drupal::logger('entity_usage.batch')->error('Batch failed during operation: @operation with arguments: @args', [
        '@operation' => $error_operation[0],
        '@args' => print_r($error_operation[0], TRUE),
      ]);
    }
  }

}
