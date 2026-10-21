<?php

declare(strict_types=1);

namespace Drupal\mass_entity_usage\Hook;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\content_moderation\Entity\ContentModerationState;
use Drupal\content_moderation\Entity\ContentModerationStateInterface;
use Drupal\entity_usage\EntityUsageInterface;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Entity Usage Queue Tracking hook implementations.
 */
final class EntityUsageQueueTrackingHooks {

  /**
   * The logger.
   */
  private readonly LoggerInterface $logger;

  /**
   * Constructs Entity Usage Queue Tracking hook implementations.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityUsageInterface $entityUsage,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->logger = $logger_factory->get('mass_entity_usage');
  }

  /**
   * Removes node and paragraph usage records for unpublished/trash nodes.
   *
   * When a node is in unpublished or trash moderation state, remove all
   * entity_usage records for that node and for its paragraphs. The queue worker
   * deletes the node's usage; we delete usage where a paragraph on this node is
   * the source. No usage is kept when the source has no published version (the
   * default revision is unpublished).
   *
   * @see \Drupal\entity_usage_queue_tracking\Plugin\QueueWorker\EntityUsageTracker::processItem()
   */
  #[Hook('entity_usage_queue_tracking_should_remove_usage')]
  public function entityUsageQueueTrackingShouldRemoveUsage(EntityInterface $entity): bool {
    if ($entity->getEntityTypeId() !== 'node' || !$entity instanceof FieldableEntityInterface) {
      return FALSE;
    }

    $content_moderation_state = ContentModerationState::loadFromModeratedEntity($entity);
    if (!$content_moderation_state instanceof ContentModerationStateInterface
      || $content_moderation_state->get('moderation_state')->isEmpty()) {
      return FALSE;
    }

    $state = $content_moderation_state->get('moderation_state')->value;
    $remove_states = [
      MassModeration::UNPUBLISHED,
      MassModeration::TRASH,
    ];
    if (!in_array($state, $remove_states, TRUE)) {
      return FALSE;
    }

    foreach ($this->getParagraphIdsFromNode($entity) as $paragraph_id) {
      $this->entityUsage->deleteBySourceEntity($paragraph_id, 'paragraph');
    }
    $this->cacheTagsInvalidator->invalidateTags(['config:views.view.report_orphaned_documents']);
    return TRUE;
  }

  /**
   * Deletes detached paragraph usage when a default node revision is updated.
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if (!$entity instanceof NodeInterface || !$entity instanceof FieldableEntityInterface) {
      return;
    }
    if (!$entity->isDefaultRevision()) {
      return;
    }

    $original = $entity->getOriginal();
    if ($original instanceof FieldableEntityInterface
      && $this->getRootParagraphIdsFromNode($entity) === $this->getRootParagraphIdsFromNode($original)) {
      return;
    }

    $this->deleteStaleParagraphUsage($entity);
  }

  /**
   * Collects root paragraph IDs referenced by a node.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $node
   *   The node entity.
   *
   * @return int[]
   *   Unique root paragraph entity IDs.
   */
  private function getRootParagraphIdsFromNode(FieldableEntityInterface $node): array {
    $root_ids = [];
    foreach ($node->getFieldDefinitions() as $name => $definition) {
      if ($definition->getType() !== 'entity_reference_revisions') {
        continue;
      }
      if ($definition->getSetting('target_type') !== 'paragraph') {
        continue;
      }
      foreach ($node->get($name) as $item) {
        if ($item->target_id) {
          $root_ids[(int) $item->target_id] = TRUE;
        }
      }
    }

    $root_ids = array_keys($root_ids);
    sort($root_ids);
    return $root_ids;
  }

  /**
   * Collects paragraph IDs referenced by a node.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $node
   *   The node entity.
   *
   * @return int[]
   *   Unique paragraph entity IDs, including nested child paragraphs.
   */
  private function getParagraphIdsFromNode(FieldableEntityInterface $node): array {
    $root_ids = $this->getRootParagraphIdsFromNode($node);
    if (!$root_ids) {
      return [];
    }

    return $this->getNestedParagraphIds($root_ids);
  }

  /**
   * Expands a list of paragraph IDs to include all nested child paragraphs.
   *
   * This walks paragraph -> paragraph references (entity_reference_revisions
   * with target_type = 'paragraph') starting from the given IDs.
   *
   * @param int[] $paragraph_ids
   *   Root paragraph entity IDs.
   *
   * @return int[]
   *   All unique paragraph IDs reachable from the roots, including roots.
   */
  private function getNestedParagraphIds(array $paragraph_ids): array {
    $storage = $this->entityTypeManager->getStorage('paragraph');
    $seen = [];
    $queue = $paragraph_ids;

    while ($queue) {
      $id = array_pop($queue);
      if (isset($seen[$id])) {
        continue;
      }
      $seen[$id] = TRUE;

      /** @var \Drupal\paragraphs\Entity\ParagraphInterface|null $paragraph */
      $paragraph = $storage->load($id);
      if (!$paragraph) {
        continue;
      }

      foreach ($paragraph->getFieldDefinitions() as $name => $definition) {
        if ($definition->getType() !== 'entity_reference_revisions') {
          continue;
        }
        if ($definition->getSetting('target_type') !== 'paragraph') {
          continue;
        }
        foreach ($paragraph->get($name) as $item) {
          if ($item->target_id && !isset($seen[$item->target_id])) {
            $queue[] = $item->target_id;
          }
        }
      }
    }

    return array_keys($seen);
  }

  /**
   * Expands paragraph IDs using paragraph parent back-references in storage.
   *
   * @param int[] $paragraph_ids
   *   Root paragraph entity IDs.
   *
   * @return int[]
   *   All unique paragraph IDs in the detached trees, including roots.
   */
  private function getNestedParagraphIdsFromStorage(array $paragraph_ids): array {
    $seen = [];
    $frontier = array_values(array_unique(array_map('intval', $paragraph_ids)));

    while ($frontier) {
      foreach ($frontier as $id) {
        $seen[$id] = TRUE;
      }

      $children = $this->database->select('paragraphs_item_field_data', 'pifd')
        ->fields('pifd', ['id'])
        ->condition('parent_type', 'paragraph')
        ->condition('parent_id', $frontier, 'IN')
        ->distinct()
        ->execute()
        ->fetchCol();

      $next_ids = [];
      foreach ($children as $child_id) {
        $child_id = (int) $child_id;
        if (!isset($seen[$child_id])) {
          $next_ids[$child_id] = TRUE;
        }
      }
      $frontier = array_keys($next_ids);
    }

    return array_keys($seen);
  }

  /**
   * Deletes paragraph-source usage rows no longer reachable from a node.
   *
   * When a node revision replaces or removes a paragraph tree, older paragraph
   * entities can remain in storage long enough for their entity_usage rows to
   * persist. Compare all paragraph trees currently parented to the node against
   * the node's active paragraph tree and delete usage rows for detached
   * paragraphs.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $node
   *   The updated node entity.
   */
  private function deleteStaleParagraphUsage(FieldableEntityInterface $node): void {
    // Cheap guard: collect the node's current root paragraph ids straight from
    // the field values (no entity loads), and the root paragraphs still
    // parented to this node in storage (one query). A paragraph has a single
    // parent, so a root no longer referenced by the current revision means its
    // whole subtree is detached. If no root is detached there is nothing stale
    // to delete.
    $current_root_ids = array_fill_keys(
      $this->getRootParagraphIdsFromNode($node),
      TRUE
    );

    $db_root_ids = $this->database
      ->select('paragraphs_item_field_data', 'pifd')
      ->fields('pifd', ['id'])
      ->condition('parent_type', 'node')
      ->condition('parent_id', $node->id())
      ->distinct()
      ->execute()
      ->fetchCol();

    $detached_roots = [];
    foreach ($db_root_ids as $root_id) {
      $root_id = (int) $root_id;
      if (!isset($current_root_ids[$root_id])) {
        $detached_roots[] = $root_id;
      }
    }
    if (!$detached_roots) {
      return;
    }

    // Narrow the detached paragraph tree to sources that still have usage
    // rows. This adds one indexed lookup, but avoids no-op delete calls and
    // misleading audit logs for detached paragraphs that have no tracked usage.
    $stale_ids = $this->database->select('entity_usage', 'eu')
      ->fields('eu', ['source_id'])
      ->condition('source_type', 'paragraph')
      ->condition('source_id', $this->getNestedParagraphIdsFromStorage($detached_roots), 'IN')
      ->distinct()
      ->execute()
      ->fetchCol();
    if (!$stale_ids) {
      return;
    }

    $stale_ids = array_map('intval', $stale_ids);
    sort($stale_ids);
    $this->logger->info('Deleting stale paragraph entity_usage records for node @node_id: @count paragraph source(s): @paragraph_ids.', [
      '@node_id' => $node->id(),
      '@count' => count($stale_ids),
      '@paragraph_ids' => implode(', ', $stale_ids),
    ]);
    foreach ($stale_ids as $paragraph_id) {
      $this->entityUsage->deleteBySourceEntity($paragraph_id, 'paragraph');
    }
  }

}
