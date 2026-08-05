<?php

declare(strict_types=1);

namespace Drupal\trashbin;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Selects entity IDs eligible for trashbin purge (moderation state trash).
 */
final class TrashbinPurgeCandidateQuery {

  /**
   * Validated entity type definitions, keyed by entity type ID.
   *
   * Memoized because validatePurgeable() runs a schema introspection query
   * and is reached once per candidate during a purge run.
   *
   * @var \Drupal\Core\Entity\ContentEntityTypeInterface[]
   */
  private array $validated = [];

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
   *   When the entity type cannot be purged (see validatePurgeable()).
   */
  public function getCandidateIds(string $entity_type_id, int $max, int $cutoff): array {
    [$query, $id_key, $activity] = $this->buildCandidateQuery($entity_type_id, $cutoff);
    $query->fields('b', [$id_key]);
    $query->range(0, $max);
    $query->addExpression($activity, 'trash_last_activity');
    $query->orderBy('trash_last_activity', 'ASC');
    $query->orderBy('b.' . $id_key, 'ASC');

    return $query->execute()->fetchCol();
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
   *   When the entity type cannot be purged (see validatePurgeable()).
   */
  public function isEntityEligible(string $entity_type_id, int|string $entity_id, int $cutoff): bool {
    [$query, $id_key] = $this->buildCandidateQuery($entity_type_id, $cutoff);
    $query->addField('b', $id_key);
    $query->condition('b.' . $id_key, $entity_id);
    $query->range(0, 1);

    return $query->execute()->fetchField() !== FALSE;
  }

  /**
   * Counts trash records excluded because a newer moderation record exists.
   *
   * These rows never become purge candidates; a persistently non-zero count
   * signals stale workflow-migration data worth cleaning up.
   *
   * @throws \InvalidArgumentException
   *   When the entity type cannot be purged (see validatePurgeable()).
   */
  public function countShadowedTrashRows(string $entity_type_id): int {
    $definition = $this->validatePurgeable($entity_type_id);
    $base_table = $definition->getDataTable() ?: $definition->getBaseTable();
    $id_key = $definition->getKey('id');

    $query = $this->connection->select($base_table, 'b');
    $this->joinModerationState($query, $definition, $entity_type_id);
    $query->condition('md.moderation_state', 'trash');
    // A trash row shadowed by a newer trash row is still purgeable through
    // the newest row, so only non-trash shadows count as "never purged".
    $query->exists($this->newerModerationRecordQuery(TRUE));
    $query->addExpression('COUNT(DISTINCT b.' . $id_key . ')');

    return (int) $query->execute()->fetchField();
  }

  /**
   * Builds the shared purge candidate SELECT (joins, trash, cutoff).
   *
   * @return array{0: \Drupal\Core\Database\Query\SelectInterface, 1: string, 2: string}
   *   Query, entity ID key, and the last-activity SQL expression.
   *
   * @throws \InvalidArgumentException
   *   When the entity type cannot be purged (see validatePurgeable()).
   */
  private function buildCandidateQuery(string $entity_type_id, int $cutoff): array {
    $definition = $this->validatePurgeable($entity_type_id);
    $base_table = $definition->getDataTable() ?: $definition->getBaseTable();
    $id_key = $definition->getKey('id');
    $rev_key = $definition->getKey('revision');
    $revision_table = $definition->getRevisionTable();
    $revision_created_key = $definition->getRevisionMetadataKey('revision_created');

    $query = $this->connection->select($base_table, 'b');
    $this->joinModerationState($query, $definition, $entity_type_id);

    $query->innerJoin(
      $revision_table,
      'rt',
      'rt.' . $rev_key . ' = b.' . $rev_key . ' AND rt.' . $id_key . ' = b.' . $id_key
    );

    $query->condition('md.moderation_state', 'trash');

    // Workflow migrations can leave an entity with two moderation records
    // pointing at the same revision; a stale "trash" row shadowed by a newer
    // record for the same revision must not make the entity purgeable.
    // "Newest id" is a proxy for "the record of the workflow currently
    // assigned to the bundle": it can only under-select, never over-select,
    // and the command's entity-API re-check remains the final authority —
    // do not drop that check as redundant.
    $query->notExists($this->newerModerationRecordQuery());

    $activity = 'GREATEST(b.changed, COALESCE(rt.' . $revision_created_key . ', b.changed))';
    $query->where($activity . ' < :cutoff', [':cutoff' => $cutoff]);

    return [$query, $id_key, $activity];
  }

  /**
   * Joins content_moderation_state_field_data as "md" for the current revision.
   */
  private function joinModerationState(SelectInterface $query, EntityTypeInterface $definition, string $entity_type_id): void {
    $id_key = $definition->getKey('id');
    $rev_key = $definition->getKey('revision');

    $join = 'md.content_entity_type_id = :etype AND md.content_entity_id = b.' . $id_key . ' AND md.content_entity_revision_id = b.' . $rev_key;
    if ($definition->isTranslatable()) {
      // Both tables carry one row per translation: correlate the langcodes
      // and decide on the default translation only, so a translated entity
      // matches once and a trashed non-default translation alone never
      // deletes the whole entity.
      $default_langcode_key = $definition->getKey('default_langcode') ?: 'default_langcode';
      $join .= ' AND md.langcode = b.langcode';
      $query->condition('b.' . $default_langcode_key, 1);
    }
    $query->innerJoin('content_moderation_state_field_data', 'md', $join, [':etype' => $entity_type_id]);
  }

  /**
   * Correlated subquery: a newer moderation record for the same revision.
   */
  private function newerModerationRecordQuery(bool $non_trash_only = FALSE): SelectInterface {
    $newer = $this->connection->select('content_moderation_state_field_data', 'md2');
    $newer->addExpression('1');
    $newer->where('md2.content_entity_type_id = md.content_entity_type_id AND md2.content_entity_id = md.content_entity_id AND md2.content_entity_revision_id = md.content_entity_revision_id AND md2.id > md.id');
    if ($non_trash_only) {
      $newer->condition('md2.moderation_state', 'trash', '<>');
    }
    return $newer;
  }

  /**
   * Validates that an entity type is structurally purgeable.
   *
   * @throws \InvalidArgumentException
   *   When the entity type has no data/base table, is not revisionable, does
   *   not track a changed time, or does not record a revision creation time
   *   in its revision table.
   */
  private function validatePurgeable(string $entity_type_id): ContentEntityTypeInterface {
    if (isset($this->validated[$entity_type_id])) {
      return $this->validated[$entity_type_id];
    }

    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $definition = $storage->getEntityType();

    $base_table = $definition->getDataTable() ?: $definition->getBaseTable();
    if (!$base_table) {
      throw new \InvalidArgumentException(sprintf('Entity type "%s" does not have a base/data table and cannot be purged.', $entity_type_id));
    }

    if (!$definition->getKey('id') || !$definition->getKey('revision')) {
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

    if (!$this->connection->schema()->fieldExists($revision_table, $revision_created_key)) {
      throw new \InvalidArgumentException(sprintf('Entity type "%s" stores its revision creation time outside "%s" and cannot be purged.', $entity_type_id, $revision_table));
    }

    if (!$this->connection->schema()->fieldExists($base_table, 'changed')) {
      throw new \InvalidArgumentException(sprintf('Entity type "%s" stores its changed time outside "%s" and cannot be purged.', $entity_type_id, $base_table));
    }

    return $this->validated[$entity_type_id] = $definition;
  }

}
