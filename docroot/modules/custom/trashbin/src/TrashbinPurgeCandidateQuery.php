<?php

declare(strict_types=1);

namespace Drupal\trashbin;

use Drupal\Core\Database\Connection;
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
   */
  public function getCandidateIds(string $entity_type_id, int $max, int $cutoff): array {
    $built = $this->buildCandidateQuery($entity_type_id, $cutoff);
    if ($built === NULL) {
      return [];
    }

    [$query, $id_key, $rt_timestamp] = $built;
    $query->fields('b', [$id_key]);
    $query->range(0, $max);
    $query->addExpression('GREATEST(b.changed, ' . $rt_timestamp . ')', 'trash_last_activity');
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
   */
  public function isEntityEligible(string $entity_type_id, int|string $entity_id, int $cutoff): bool {
    $built = $this->buildCandidateQuery($entity_type_id, $cutoff);
    if ($built === NULL) {
      return FALSE;
    }

    [$query, $id_key] = $built;
    $query->addField('b', $id_key);
    $query->condition('b.' . $id_key, $entity_id);
    $query->range(0, 1);

    return (bool) $query->execute()->fetchField();
  }

  /**
   * Builds the shared purge candidate SELECT (joins, trash, cutoff).
   *
   * @return array{0: \Drupal\Core\Database\Query\SelectInterface, 1: string, 2: string}|null
   *   Query, entity ID key, and revision timestamp field expression; or NULL.
   */
  private function buildCandidateQuery(string $entity_type_id, int $cutoff): ?array {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $definition = $storage->getEntityType();

    $base_table = $definition->getDataTable() ?: $definition->getBaseTable();
    if (!$base_table) {
      return NULL;
    }

    $id_key = $definition->getKey('id');
    $rev_key = $definition->getKey('revision');
    if (!$id_key || !$rev_key) {
      return NULL;
    }

    $query = $this->connection->select($base_table, 'b');

    $query->innerJoin(
      'content_moderation_state_field_data',
      'md',
      'md.content_entity_type_id = :etype AND md.content_entity_id = b.' . $id_key . ' AND md.content_entity_revision_id = b.' . $rev_key,
      [':etype' => $entity_type_id]
    );

    $revision_table = $definition->getRevisionTable();
    $rt_timestamp = 'rt.revision_timestamp';
    if ($entity_type_id === 'media') {
      $rt_timestamp = 'rt.revision_created';
    }

    if ($revision_table) {
      $query->innerJoin(
        $revision_table,
        'rt',
        'rt.' . $rev_key . ' = b.' . $rev_key . ' AND rt.' . $id_key . ' = b.' . $id_key
      );
    }

    $query->condition('md.moderation_state', 'trash');
    $query->where('GREATEST(b.changed, ' . $rt_timestamp . ') < :cutoff', [':cutoff' => $cutoff]);

    return [$query, $id_key, $rt_timestamp];
  }

}
