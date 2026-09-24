<?php

namespace Drupal\mass_ai_editorial;

use Drupal\Core\Database\Connection;

/**
 * Resolves organization hierarchies and their published content.
 */
final class OrganizationScope {

  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * Loads published nodes assigned to an organization scope.
   *
   * @return array<int>
   *   Node IDs ordered by most recently changed first.
   */
  public function loadPublishedNodeIds(int $org_id, ?int $limit = NULL, bool $include_descendants = FALSE): array {
    $org_ids = $include_descendants ? $this->descendantIds($org_id) : [$org_id];

    $query = $this->database->select('node_field_data', 'n');
    $query->distinct();
    $query->leftJoin('node__field_organizations', 'o', 'n.nid = o.entity_id');
    $query->fields('n', ['nid', 'changed']);
    $query->condition('n.status', 1);
    $query->condition('n.type', AiEditorialIndexer::EXCLUDED_BUNDLES, 'NOT IN');
    $query->condition('n.default_langcode', 1);
    $or = $query->orConditionGroup()
      ->condition('n.nid', $org_ids, 'IN')
      ->condition('o.field_organizations_target_id', $org_ids, 'IN');
    $query->condition($or);
    $query->orderBy('n.changed', 'DESC');
    if ($limit !== NULL) {
      $query->range(0, $limit);
    }

    return array_map('intval', $query->execute()->fetchCol());
  }

  /**
   * Returns an organization and all organizations below it.
   *
   * @return array<int>
   *   Organization node IDs in breadth-first order.
   */
  public function descendantIds(int $org_id): array {
    return $this->traverse([$org_id], 'field_parent_target_id', 'entity_id');
  }

  /**
   * Returns organizations and all organizations above them.
   *
   * @param array<int> $org_ids
   *   Organization node IDs from a content entity.
   *
   * @return array<int>
   *   Organization node IDs in breadth-first order.
   */
  public function ancestorIds(array $org_ids): array {
    return $this->traverse($org_ids, 'entity_id', 'field_parent_target_id');
  }

  /**
   * Traverses field_parent in one direction while guarding against cycles.
   *
   * @param array<int> $seed_ids
   *   Organization IDs at which to begin.
   * @param string $match_column
   *   Column matched against the current traversal frontier.
   * @param string $result_column
   *   Column containing the next traversal frontier.
   *
   * @return array<int>
   *   Unique IDs including the seed IDs.
   */
  private function traverse(array $seed_ids, string $match_column, string $result_column): array {
    $seed_ids = array_values(array_unique(array_filter(array_map('intval', $seed_ids))));
    $seen = array_fill_keys($seed_ids, TRUE);
    $frontier = $seed_ids;

    while ($frontier) {
      $query = $this->database->select('node__field_parent', 'p');
      $query->fields('p', [$result_column]);
      $query->condition('p.deleted', 0);
      $query->condition("p.$match_column", $frontier, 'IN');
      $related_ids = array_map('intval', $query->execute()->fetchCol());
      $frontier = [];

      foreach ($related_ids as $related_id) {
        if ($related_id <= 0 || isset($seen[$related_id])) {
          continue;
        }
        $seen[$related_id] = TRUE;
        $frontier[] = $related_id;
      }
    }

    return array_map('intval', array_keys($seen));
  }

}
