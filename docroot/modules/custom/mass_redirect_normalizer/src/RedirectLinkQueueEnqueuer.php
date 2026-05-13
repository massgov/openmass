<?php

namespace Drupal\mass_redirect_normalizer;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;

/**
 * Enqueues redirect-link normalization work on the core queue.
 */
final class RedirectLinkQueueEnqueuer {

  public const QUEUE_NAME = 'mass_redirect_normalizer_link_normalization';

  private const PENDING_STATE_KEY = 'mass_redirect_normalizer.queue_pending_keys';

  /**
   * How often to persist dedupe keys during bulk enqueues (aligned with Drush sweep batching).
   */
  private const PENDING_FLUSH_INTERVAL = 500;

  /**
   * In-request copy of pending keys to avoid thousands of state reads/writes.
   *
   * @var array<string, bool>|null
   */
  private ?array $pendingWorkingCopy = NULL;

  /**
   * Mutations since last persist when using $pendingWorkingCopy.
   */
  private int $pendingMutationsSincePersist = 0;

  public function __construct(
    protected QueueFactory $queueFactory,
    protected StateInterface $state,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RedirectLinkNormalizationEligibility $eligibility,
  ) {}

  /**
   * Enqueues an entity ID without loading the entity or checking eligibility.
   *
   * Eligibility and normalization run in the queue worker. Use this for bulk
   * Drush sweeps; use enqueueEntity() from presave where the entity is already
   * loaded.
   *
   * @return string
   *   One of: enqueued, already_queued, skipped (invalid type/id only).
   */
  public function enqueueId(string $entityType, int $entityId, string $source = 'drush'): string {
    if (!in_array($entityType, ['node', 'paragraph'], TRUE) || $entityId <= 0) {
      return 'skipped';
    }

    return $this->addToQueue($entityType, $entityId, $source);
  }

  /**
   * Enqueues one entity for later normalization.
   *
   * @return string
   *   One of: enqueued, already_queued, skipped.
   */
  public function enqueueEntity(ContentEntityInterface $entity, string $source = 'presave'): string {
    $entityType = $entity->getEntityTypeId();
    if (!in_array($entityType, ['node', 'paragraph'], TRUE)) {
      return 'skipped';
    }

    $entityId = (int) $entity->id();
    if ($entityId <= 0) {
      return 'skipped';
    }

    if (!$this->eligibility->isEligible($entityType, $entity)) {
      return 'skipped';
    }

    return $this->addToQueue($entityType, $entityId, $source);
  }

  /**
   * Adds to queue without re-checking eligibility (caller already verified).
   *
   * Used when Drush has loaded the entity and run eligibility or dry-run checks.
   *
   * @return string
   *   One of: enqueued, already_queued, skipped.
   */
  public function enqueueVerified(ContentEntityInterface $entity, string $source = 'drush'): string {
    $entityType = $entity->getEntityTypeId();
    if (!in_array($entityType, ['node', 'paragraph'], TRUE)) {
      return 'skipped';
    }
    $entityId = (int) $entity->id();
    if ($entityId <= 0) {
      return 'skipped';
    }
    return $this->addToQueue($entityType, $entityId, $source);
  }

  /**
   * Enqueues a loaded entity by type and ID.
   */
  public function enqueueById(string $entityType, int $entityId, string $source = 'drush'): string {
    if (!in_array($entityType, ['node', 'paragraph'], TRUE) || $entityId <= 0) {
      return 'skipped';
    }

    $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);
    if (!$entity instanceof ContentEntityInterface) {
      return 'skipped';
    }

    return $this->enqueueEntity($entity, $source);
  }

  /**
   * Creates a queue item if not already pending.
   *
   * @return string
   *   enqueued, already_queued.
   */
  private function addToQueue(string $entityType, int $entityId, string $source): string {
    $itemKey = $this->buildItemKey($entityType, $entityId);
    if ($this->isPending($itemKey)) {
      return 'already_queued';
    }

    $this->queueFactory->get(self::QUEUE_NAME)->createItem([
      'entity_type' => $entityType,
      'entity_id' => $entityId,
      'source' => $source,
    ]);
    $this->markPending($itemKey);
    return 'enqueued';
  }

  /**
   * Loads pending dedupe keys once per PHP request when needed.
   */
  private function ensurePendingLoaded(): void {
    if ($this->pendingWorkingCopy !== NULL) {
      return;
    }
    $pending = $this->state->get(self::PENDING_STATE_KEY, []);
    $this->pendingWorkingCopy = is_array($pending) ? $pending : [];
  }

  /**
   * Writes the working pending map to state storage.
   */
  private function persistPendingWorkingCopy(): void {
    if ($this->pendingWorkingCopy === NULL) {
      return;
    }
    if ($this->pendingWorkingCopy === []) {
      $this->state->delete(self::PENDING_STATE_KEY);
      return;
    }
    $this->state->set(self::PENDING_STATE_KEY, $this->pendingWorkingCopy);
  }

  /**
   * Persists any batched pending dedupe keys (call between bulk phases).
   */
  public function flushPendingDedupeState(): void {
    if ($this->pendingWorkingCopy === NULL) {
      return;
    }
    $this->persistPendingWorkingCopy();
    $this->pendingMutationsSincePersist = 0;
  }

  /**
   * Clears pending dedupe state for one queue item.
   */
  public function clearPending(string $entityType, int $entityId): void {
    $itemKey = $this->buildItemKey($entityType, $entityId);
    $this->ensurePendingLoaded();
    if (!array_key_exists($itemKey, $this->pendingWorkingCopy)) {
      return;
    }
    unset($this->pendingWorkingCopy[$itemKey]);
    $this->persistPendingWorkingCopy();
  }

  /**
   * Builds a stable dedupe key for queue items.
   */
  public function buildItemKey(string $entityType, int $entityId): string {
    return $entityType . ':' . $entityId;
  }

  /**
   * Returns TRUE when the entity is already pending in the queue.
   */
  private function isPending(string $itemKey): bool {
    $this->ensurePendingLoaded();
    return !empty($this->pendingWorkingCopy[$itemKey]);
  }

  /**
   * Marks one entity as pending in queue dedupe state.
   */
  private function markPending(string $itemKey): void {
    $this->ensurePendingLoaded();
    $this->pendingWorkingCopy[$itemKey] = TRUE;
    $this->pendingMutationsSincePersist++;
    if ($this->pendingMutationsSincePersist >= self::PENDING_FLUSH_INTERVAL) {
      $this->persistPendingWorkingCopy();
      $this->pendingMutationsSincePersist = 0;
    }
  }

}
