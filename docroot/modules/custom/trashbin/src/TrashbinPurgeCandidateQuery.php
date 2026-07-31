<?php

declare(strict_types=1);

namespace Drupal\trashbin;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Selects entity IDs eligible for trashbin purge (moderation state trash).
 */
final class TrashbinPurgeCandidateQuery {

  public function __construct(
    private Connection $connection,
    private EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Returns entity IDs to purge, oldest last-activity first.
   *
   * @param string $entity_type_id
   *   Entity type ID (e.g. node, media).
   * @param int $max
   *   Maximum number of IDs to return.
   * @param int $cutoff
   *   Unix timestamp; entities whose GREATEST(changed, revision time) is
   *   strictly less than this value are eligible.
   *
   * @return int[]|string[]
   *   Entity IDs in purge order.
   *
   * @throws \InvalidArgumentException
   *   When the entity type cannot be purged (see buildCandidateQuery()).
   */
  public function getCandidateIds(string $entity_type_id, int $max, int $cutoff): array {
    [$query, $id_key, $activity] = $this->buildCandidateQuery($entity_type_id, $cutoff);
    $query->fields('b', [$id_key]);
    $query->range(0, $max);
    $query->addExpression($activity, 'trash_last_activity');
    $query->orderBy('trash_last_activity', 'ASC');
    $query->orderBy('b.' . $id_key, 'ASC');

    return $query->execute()->fetchCol() ?: [];
  }

  /**
   * Re-checks trash state and cutoff immediately before delete.
   *
   * @param string $entity_type_id
   *   Entity type ID (e.g. node, media).
   * @param int|string $entity_id
   *   Entity ID.
   * @param int $cutoff
   *   Unix timestamp cutoff captured at command start.
   *
   * @throws \InvalidArgumentException
   *   When the entity type cannot be purged (see buildCandidateQuery()).
   */
  public function isEntityEligible(string $entity_type_id, int|string $entity_id, int $cutoff): bool {
    [$query, $id_key] = $this->buildCandidateQuery($entity_type_id, $cutoff);
    $query->addField('b', $id_key);
    $query->condition('b.' . $id_key, $entity_id);
    $query->range(0, 1);

    return (bool) $query->execute()->fetchField();
  }

  /**
   * Builds the shared purge candidate SELECT (joins, trash, cutoff).
   *
   * @return array{0: \Drupal\Core\Database\Query\SelectInterface, 1: string, 2: string}
   *   Query, entity ID key, and the last-activity SQL expression.
   *
   * @throws \InvalidArgumentException
   *   When the entity type has no data/base table, is not revisionable, does
   *   not track a changed time, or does not record a revision creation time.
   */
  private function buildCandidateQuery(string $entity_type_id, int $cutoff): array {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $definition = $storage->getEntityType();

    $base_table = $definition->getDataTable() ?: $definition->getBaseTable();
    if (!$base_table) {
      throw new \InvalidArgumentException(sprintf('Entity type "%s" does not have a base/data table and cannot be purged.', $entity_type_id));
    }

    $id_key = $definition->getKey('id');
    $rev_key = $definition->getKey('revision');
    if (!$id_key || !$rev_key) {
      throw new \InvalidArgumentException(sprintf('Entity type "%s" must be revisionable to use trashbin purge (missing id/revision keys).', $entity_type_id));
    }

    $revision_table = $definition->getRevisionTable();
    if (!$revision_table) {
      throw new \InvalidArgumentException(sprintf('Entity type "%s" does not have a revision table and cannot be purged.', $entity_type_id));
    }

    if (!$definition->entityClassImplements(EntityChangedInterface::class)) {
      throw new \InvalidArgumentException(sprintf('Entity type "%s" does not track a changed time and cannot be purged.', $entity_type_id));
    }

    $revision_created_key = $definition instanceof ContentEntityTypeInterface
      ? $definition->getRevisionMetadataKey('revision_created')
      : NULL;
    if (!$revision_created_key) {
      throw new \InvalidArgumentException(sprintf('Entity type "%s" does not record a revision creation time and cannot be purged.', $entity_type_id));
    }

    $query = $this->connection->select($base_table, 'b');

    $moderation_join = 'md.content_entity_type_id = :etype AND md.content_entity_id = b.' . $id_key . ' AND md.content_entity_revision_id = b.' . $rev_key;
    if ($definition->isTranslatable()) {
      // Both tables carry one row per translation: correlate the langcodes
      // and decide on the default translation only, so a translated entity
      // matches once and a trashed non-default translation alone never
      // deletes the whole entity.
      $moderation_join .= ' AND md.langcode = b.langcode';
      $query->condition('b.default_langcode', 1);
    }
    $query->innerJoin('content_moderation_state_field_data', 'md', $moderation_join, [':etype' => $entity_type_id]);

    $query->innerJoin(
      $revision_table,
      'rt',
      'rt.' . $rev_key . ' = b.' . $rev_key . ' AND rt.' . $id_key . ' = b.' . $id_key
    );

    $query->condition('md.moderation_state', 'trash');

    // Workflow migrations can leave an entity with two moderation records
    // pointing at the same revision; only the newest reflects the workflow
    // currently assigned to the bundle. A stale "trash" row shadowed by a
    // newer record for the same revision must not make the entity purgeable.
    $newer = $this->connection->select('content_moderation_state_field_data', 'md2');
    $newer->addExpression('1');
    $newer->where('md2.content_entity_type_id = md.content_entity_type_id AND md2.content_entity_id = md.content_entity_id AND md2.content_entity_revision_id = md.content_entity_revision_id AND md2.id > md.id');
    $query->notExists($newer);

    $activity = 'GREATEST(b.changed, COALESCE(rt.' . $revision_created_key . ', b.changed))';
    $query->where($activity . ' < :cutoff', [':cutoff' => $cutoff]);

    return [$query, $id_key, $activity];
  }

}
