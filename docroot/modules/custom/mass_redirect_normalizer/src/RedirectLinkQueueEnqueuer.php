<?php

namespace Drupal\mass_redirect_normalizer;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * Enqueues redirect-link normalization work on the core queue.
 */
final class RedirectLinkQueueEnqueuer {

  public const QUEUE_NAME = 'mass_redirect_normalizer_link_normalization';

  /**
   * State key: set while mnrl enqueue sweep is running (hourly drain should skip).
   */
  public const SWEEP_IN_PROGRESS_STATE_KEY = 'mass_redirect_normalizer.sweep_in_progress';

  /**
   * Entity types that participate in redirect link normalization.
   *
   * @var list<string>
   */
  public const SUPPORTED_ENTITY_TYPES = ['node', 'paragraph'];

  public const QUEUE_ITEM_BATCH_SIZE = 100;

  /**
   * Buffered entity refs to write in a single queue row.
   *
   * @var array<int, array{entity_type:string, entity_id:int}>
   */
  private array $bulkQueueBuffer = [];

  public function __construct(
    protected QueueFactory $queueFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RedirectLinkNormalizationEligibility $eligibility,
  ) {}

  /**
   * Buffers a bulk sweep entity reference and flushes by batch size.
   *
   * @return string
   *   One of: enqueued, skipped.
   */
  public function enqueueIdBulk(string $entityType, int $entityId, string $source = 'drush'): string {
    if (!self::isSupportedEntityType($entityType) || $entityId <= 0) {
      return 'skipped';
    }

    $this->bulkQueueBuffer[] = [
      'entity_type' => $entityType,
      'entity_id' => $entityId,
    ];
    if (count($this->bulkQueueBuffer) >= self::QUEUE_ITEM_BATCH_SIZE) {
      $this->flushEnqueueBuffers($source);
    }
    return 'enqueued';
  }

  /**
   * Removes all pending normalization queue items and clears the bulk buffer.
   *
   * @return int
   *   Number of queue rows deleted.
   */
  public function purgeNormalizationQueue(): int {
    $this->bulkQueueBuffer = [];
    $queue = $this->queueFactory->get(self::QUEUE_NAME);
    $count = $queue->numberOfItems();
    $queue->deleteQueue();
    return $count;
  }

  /**
   * Flushes queued entity refs into one queue row.
   */
  public function flushEnqueueBuffers(string $source = 'drush'): void {
    if ($this->bulkQueueBuffer === []) {
      return;
    }
    $this->queueFactory->get(self::QUEUE_NAME)->createItem([
      'entities' => $this->bulkQueueBuffer,
      'source' => $source,
    ]);
    $this->bulkQueueBuffer = [];
  }

  /**
   * Enqueues one entity for later normalization (presave path).
   *
   * @return string
   *   One of: enqueued, skipped.
   */
  public function enqueueEntity(ContentEntityInterface $entity, string $source = 'presave'): string {
    $entityType = $entity->getEntityTypeId();
    if (!self::isSupportedEntityType($entityType)) {
      return 'skipped';
    }
    $entityId = (int) $entity->id();
    if ($entityId <= 0) {
      return 'skipped';
    }
    if (!$this->eligibility->isEligible($entityType, $entity)) {
      return 'skipped';
    }

    $this->queueFactory->get(self::QUEUE_NAME)->createItem([
      'entity_type' => $entityType,
      'entity_id' => $entityId,
      'source' => $source,
    ]);
    return 'enqueued';
  }

  /**
   * Enqueues a loaded entity by type and ID (test/helper compatibility).
   */
  public function enqueueById(string $entityType, int $entityId, string $source = 'presave'): string {
    if (!self::isSupportedEntityType($entityType) || $entityId <= 0) {
      return 'skipped';
    }
    $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);
    if (!$entity instanceof ContentEntityInterface) {
      return 'skipped';
    }
    return $this->enqueueEntity($entity, $source);
  }

  /**
   * Whether this entity type is handled by the normalization queue.
   */
  private static function isSupportedEntityType(string $entityType): bool {
    return in_array($entityType, self::SUPPORTED_ENTITY_TYPES, TRUE);
  }

}
