<?php

namespace Drupal\mass_entity_usage;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
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
  const PROGRESS_STATE_PREFIX = 'mass_entity_usage.queue_progress.';
  const ENQUEUE_COMPLETED_AT_KEY = 'mass_entity_usage.enqueue_completed_at';
  const ENQUEUE_COMPLETED_TTL = 86400;
  const ENQUEUE_RUN_ID_KEY = 'mass_entity_usage.enqueue_run_id';
  const QUEUE_NAME = 'entity_usage_tracker';

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
   * State storage.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Creates a EntityUsageQueueBatchManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   State storage.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TranslationInterface $string_translation, ConfigFactoryInterface $config_factory, ?StateInterface $state = NULL, ?Connection $database = NULL) {
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $string_translation;
    $this->config = $config_factory->get('entity_usage.settings');
    $this->state = $state ?? \Drupal::state();
    $this->database = $database ?? \Drupal::database();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('string_translation'),
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('database')
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
    if (empty($batch['operations'])) {
      return FALSE;
    }
    batch_set($batch);
    return TRUE;
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
      if ($track_this_entity_type && !$this->isEnqueueCompleted($entity_type_id)) {
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
   * Gets tracked source entity type IDs.
   *
   * @return string[]
   *   Tracked source entity type IDs.
   */
  public function getTrackedSourceEntityTypes() {
    $tracked_types = [];
    $to_track = $this->config->get('track_enabled_source_entity_types');
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      // Only look for entities enabled for tracking on the settings form.
      $track_this_entity_type = FALSE;
      if (!is_array($to_track) && ($entity_type->entityClassImplements('\Drupal\Core\Entity\ContentEntityInterface'))) {
        // When no settings are defined, track all content entities by default,
        // except for Files and Users.
        if (!in_array($entity_type_id, ['file', 'user'], TRUE)) {
          $track_this_entity_type = TRUE;
        }
      }
      elseif (is_array($to_track) && in_array($entity_type_id, $to_track, TRUE)) {
        $track_this_entity_type = TRUE;
      }
      if ($track_this_entity_type) {
        $tracked_types[] = $entity_type_id;
      }
    }

    return $tracked_types;
  }

  /**
   * Whether enqueue has finished for an entity type.
   */
  public function isEnqueueCompleted($entity_type_id) {
    $progress_state = $this->getProgressState($entity_type_id);
    if (!static::progressBelongsToCurrentRun($progress_state, $this->getEnqueueRunId())) {
      return FALSE;
    }
    return is_array($progress_state) && !empty($progress_state['completed']);
  }

  /**
   * Clears enqueue state, queue items, and starts a new run identifier.
   */
  public function beginFreshEnqueueRun(): void {
    $this->clearTrackedProgress();
    \Drupal::queue(static::QUEUE_NAME)->deleteQueue();
    $this->database->delete('queue_unique')
      ->condition('name', static::QUEUE_NAME)
      ->execute();
    $this->state->set(static::ENQUEUE_RUN_ID_KEY, $this->createEnqueueRunId());
  }

  /**
   * Ensures an enqueue run identifier exists for resume operations.
   */
  public function ensureEnqueueRunId(): string {
    $run_id = $this->getEnqueueRunId();
    if ($run_id !== NULL) {
      return $run_id;
    }
    foreach ($this->getTrackedSourceEntityTypes() as $entity_type_id) {
      $progress_state = $this->getProgressState($entity_type_id);
      if (!is_array($progress_state)) {
        continue;
      }
      $progress_run_id = $progress_state['run_id'] ?? NULL;
      if (is_string($progress_run_id) && $progress_run_id !== '') {
        $this->state->set(static::ENQUEUE_RUN_ID_KEY, $progress_run_id);
        return $progress_run_id;
      }
    }
    $run_id = $this->createEnqueueRunId();
    $this->state->set(static::ENQUEUE_RUN_ID_KEY, $run_id);
    return $run_id;
  }

  /**
   * Syncs run IDs and progress before resuming an interrupted enqueue.
   */
  public function prepareResume(): void {
    $run_id = $this->ensureEnqueueRunId();
    foreach ($this->getTrackedSourceEntityTypes() as $entity_type_id) {
      $progress_state = $this->getProgressState($entity_type_id);
      if (!is_array($progress_state) || !empty($progress_state['completed'])) {
        continue;
      }
      if (empty($progress_state['total']) && empty($progress_state['progress'])) {
        continue;
      }
      if (empty($progress_state['run_id'])) {
        $progress_state['run_id'] = $run_id;
        $this->state->set(static::getProgressStateKey($entity_type_id), $progress_state);
      }
    }
  }

  /**
   * Returns the active enqueue run identifier.
   */
  public function getEnqueueRunId(): ?string {
    $run_id = $this->state->get(static::ENQUEUE_RUN_ID_KEY);
    return is_string($run_id) && $run_id !== '' ? $run_id : NULL;
  }

  /**
   * Creates a unique enqueue run identifier.
   */
  protected function createEnqueueRunId(): string {
    return \Drupal::time()->getRequestTime() . '.' . mt_rand();
  }

  /**
   * Whether saved progress belongs to the active enqueue run.
   *
   * @param array|null $progress_state
   *   Saved progress state.
   * @param string|null $run_id
   *   Active enqueue run identifier.
   */
  protected static function progressBelongsToCurrentRun(?array $progress_state, ?string $run_id): bool {
    if (!is_array($progress_state)) {
      return FALSE;
    }
    $progress_run_id = $progress_state['run_id'] ?? NULL;
    if ($progress_run_id !== NULL && $progress_run_id !== '') {
      return $run_id !== NULL && $progress_run_id === $run_id;
    }
    return TRUE;
  }

  /**
   * Whether enqueue has finished for all tracked entity types.
   */
  public function isAllEnqueueCompleted() {
    $tracked_types = $this->getTrackedSourceEntityTypes();
    if ($tracked_types === []) {
      return FALSE;
    }
    foreach ($tracked_types as $entity_type_id) {
      if (!$this->isEnqueueCompleted($entity_type_id)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Records that a full enqueue run finished successfully.
   */
  public function markEnqueueCompleted() {
    $this->state->set(static::ENQUEUE_COMPLETED_AT_KEY, \Drupal::time()->getRequestTime());
  }

  /**
   * Clears the enqueue completion timestamp.
   */
  public function clearEnqueueCompleted() {
    $this->state->delete(static::ENQUEUE_COMPLETED_AT_KEY);
  }

  /**
   * Whether enqueue completed within the last 24 hours.
   */
  public function wasEnqueueCompletedRecently() {
    $completed_at = $this->getEnqueueCompletedAt();
    if ($completed_at === NULL) {
      return FALSE;
    }
    return (\Drupal::time()->getRequestTime() - $completed_at) < static::ENQUEUE_COMPLETED_TTL;
  }

  /**
   * Unix timestamp when enqueue last completed, or NULL.
   */
  public function getEnqueueCompletedAt() {
    $completed_at = $this->state->get(static::ENQUEUE_COMPLETED_AT_KEY);
    return is_numeric($completed_at) ? (int) $completed_at : NULL;
  }

  /**
   * Returns per-entity-type enqueue progress for operator visibility.
   *
   * @return array<string, string>
   *   Entity type ID keyed summaries.
   */
  public function getProgressSummary() {
    $summary = [];
    $run_id = $this->getEnqueueRunId();
    foreach ($this->getTrackedSourceEntityTypes() as $entity_type_id) {
      $progress_state = $this->getProgressState($entity_type_id);
      if (!static::progressBelongsToCurrentRun($progress_state, $run_id)) {
        $summary[$entity_type_id] = 'no saved progress';
      }
      elseif (!empty($progress_state['completed'])) {
        $total = (int) ($progress_state['total'] ?? 0);
        $summary[$entity_type_id] = "completed {$total}/{$total}";
      }
      else {
        $progress = (int) ($progress_state['progress'] ?? 0);
        $total = (int) ($progress_state['total'] ?? 0);
        $summary[$entity_type_id] = "in progress {$progress}/{$total}";
      }
    }

    return $summary;
  }

  /**
   * Determines whether there is an interrupted enqueue operation to resume.
   *
   * @return bool
   *   TRUE when at least one tracked type has unfinished progress.
   */
  public function hasInterruptedProgress() {
    $this->syncEnqueueRunIdFromProgress();
    $run_id = $this->getEnqueueRunId();
    foreach ($this->getTrackedSourceEntityTypes() as $entity_type_id) {
      $progress_state = $this->getProgressState($entity_type_id);
      if (!is_array($progress_state) || !empty($progress_state['completed'])) {
        continue;
      }
      if (empty($progress_state['total']) && empty($progress_state['progress'])) {
        continue;
      }
      if (!static::progressBelongsToCurrentRun($progress_state, $run_id)) {
        continue;
      }
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Restores the active run ID from saved progress when state lost it.
   */
  protected function syncEnqueueRunIdFromProgress(): void {
    if ($this->getEnqueueRunId() !== NULL) {
      return;
    }
    foreach ($this->getTrackedSourceEntityTypes() as $entity_type_id) {
      $progress_state = $this->getProgressState($entity_type_id);
      if (!is_array($progress_state) || empty($progress_state['run_id']) || !empty($progress_state['completed'])) {
        continue;
      }
      $this->state->set(static::ENQUEUE_RUN_ID_KEY, $progress_state['run_id']);
      return;
    }
  }

  /**
   * Clears saved progress for all tracked source entity types.
   */
  public function clearTrackedProgress() {
    $keys = $this->database->select('key_value', 'kv')
      ->fields('kv', ['name'])
      ->condition('collection', 'state')
      ->condition('name', $this->database->escapeLike(static::PROGRESS_STATE_PREFIX) . '%', 'LIKE')
      ->execute()
      ->fetchCol();
    foreach ($keys as $key) {
      $this->state->delete($key);
    }
    $this->clearEnqueueCompleted();
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
    $queue = \Drupal::queue(static::QUEUE_NAME);
    $state = \Drupal::state();

    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $entity_type_key = $entity_type->getKey('id');
    $progress_state = $state->get(static::getProgressStateKey($entity_type_id));
    $run_id = static::getActiveRunId($state);

    // First pass, populate the sandbox.
    if (empty($context['sandbox']['total'])) {
      // Log the start of the batch for this entity type.
      \Drupal::logger('entity_usage.batch')->info('Starting batch process for entity type: @entity_type.', ['@entity_type' => $entity_type_id]);

      if (static::progressBelongsToCurrentRun($progress_state, $run_id) && !empty($progress_state['completed'])) {
        $context['sandbox']['progress'] = (int) ($progress_state['total'] ?? 0);
        $context['sandbox']['total'] = (int) ($progress_state['total'] ?? 0);
        $context['sandbox']['current_item'] = (int) ($progress_state['current_item'] ?? 0);
        $context['finished'] = 1;
        $context['results'][] = $entity_type_id;
        return;
      }

      if (static::progressBelongsToCurrentRun($progress_state, $run_id)
        && !empty($progress_state['total'])
        && empty($progress_state['completed'])) {
        $context['sandbox']['progress'] = (int) ($progress_state['progress'] ?? 0);
        $context['sandbox']['total'] = (int) $progress_state['total'];
        $context['sandbox']['current_item'] = (int) ($progress_state['current_item'] ?? 0);
      }
      else {
        // Delete current usage statistics only on a fresh start.
        \Drupal::service('entity_usage.usage')
          ->bulkDeleteSources($entity_type_id);

        $context['sandbox']['progress'] = 0;
        // Set the total to the number of entities.
        $context['sandbox']['total'] = (int) $entity_storage->getQuery()
          ->accessCheck(FALSE)
          ->count()
          ->execute();
        $context['sandbox']['current_item'] = 0;
      }

      // Log the total number of entities found.
      \Drupal::logger('entity_usage.batch')->info('Total entities to process for @entity_type: @total', [
        '@entity_type' => $entity_type_id,
        '@total' => $context['sandbox']['total'],
      ]);

      $state->set(static::getProgressStateKey($entity_type_id), [
        'run_id' => $run_id,
        'progress' => (int) $context['sandbox']['progress'],
        'total' => (int) $context['sandbox']['total'],
        'current_item' => (int) $context['sandbox']['current_item'],
        'completed' => FALSE,
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

      $state->set(static::getProgressStateKey($entity_type_id), [
        'run_id' => $run_id,
        'progress' => (int) $context['sandbox']['progress'],
        'total' => (int) $context['sandbox']['total'],
        'current_item' => (int) $context['sandbox']['current_item'],
        'completed' => FALSE,
      ]);

      $context['results'][] = $entity_type_id;
    }

    if ($context['sandbox']['progress'] < $context['sandbox']['total']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['total'];
    }
    else {
      $context['finished'] = 1;
      $state->set(static::getProgressStateKey($entity_type_id), [
        'run_id' => $run_id,
        'progress' => (int) $context['sandbox']['total'],
        'total' => (int) $context['sandbox']['total'],
        'current_item' => (int) $context['sandbox']['current_item'],
        'completed' => TRUE,
      ]);
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
   * Returns the queue progress state key for an entity type.
   *
   * @param string $entity_type_id
   *   Entity type ID.
   *
   * @return string
   *   State key.
   */
  protected static function getProgressStateKey($entity_type_id) {
    return static::PROGRESS_STATE_PREFIX . $entity_type_id;
  }

  /**
   * Returns the active enqueue run ID from state.
   */
  protected static function getActiveRunId(StateInterface $state): ?string {
    $run_id = $state->get(static::ENQUEUE_RUN_ID_KEY);
    return is_string($run_id) && $run_id !== '' ? $run_id : NULL;
  }

  /**
   * Returns progress state for an entity type.
   *
   * @param string $entity_type_id
   *   Entity type ID.
   *
   * @return array|null
   *   State data or NULL.
   */
  protected function getProgressState($entity_type_id) {
    return $this->state->get(static::getProgressStateKey($entity_type_id));
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
      $manager = \Drupal::service('mass_entity_usage.queue_batch_manager');
      $manager->markEnqueueCompleted();
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
