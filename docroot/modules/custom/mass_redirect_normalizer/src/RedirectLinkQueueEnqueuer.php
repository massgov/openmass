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

  public function __construct(
    protected QueueFactory $queueFactory,
    protected StateInterface $state,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RedirectLinkNormalizationEligibility $eligibility,
  ) {}

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
   * Clears pending dedupe state for one queue item.
   */
  public function clearPending(string $entityType, int $entityId): void {
    $itemKey = $this->buildItemKey($entityType, $entityId);
    $pending = $this->state->get(self::PENDING_STATE_KEY, []);
    if (!is_array($pending) || !array_key_exists($itemKey, $pending)) {
      return;
    }
    unset($pending[$itemKey]);
    if ($pending === []) {
      $this->state->delete(self::PENDING_STATE_KEY);
      return;
    }
    $this->state->set(self::PENDING_STATE_KEY, $pending);
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
    $pending = $this->state->get(self::PENDING_STATE_KEY, []);
    return is_array($pending) && !empty($pending[$itemKey]);
  }

  /**
   * Marks one entity as pending in queue dedupe state.
   */
  private function markPending(string $itemKey): void {
    $pending = $this->state->get(self::PENDING_STATE_KEY, []);
    if (!is_array($pending)) {
      $pending = [];
    }
    $pending[$itemKey] = TRUE;
    $this->state->set(self::PENDING_STATE_KEY, $pending);
  }

}
