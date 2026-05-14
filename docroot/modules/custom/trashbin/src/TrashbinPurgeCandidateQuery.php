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
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $definition = $storage->getEntityType();

    $base_table = $definition->getDataTable() ?: $definition->getBaseTable();
    if (!$base_table) {
      return [];
    }

    $id_key = $definition->getKey('id');
    $rev_key = $definition->getKey('revision');
    $changed_key = 'changed';

    if (!$id_key || !$rev_key) {
      return [];
    }

    $query = $this->connection->select($base_table, 'b')
      ->fields('b', [$id_key])
      ->range(0, $max);

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
    $query->where('GREATEST(b.' . $changed_key . ', ' . $rt_timestamp . ') < :cutoff', [':cutoff' => $cutoff]);

    $query->addExpression('GREATEST(b.' . $changed_key . ', ' . $rt_timestamp . ')', 'trash_last_activity');
    $query->orderBy('trash_last_activity', 'ASC');
    $query->orderBy('b.' . $id_key, 'ASC');

    return $query->execute()->fetchCol() ?: [];
  }

}
