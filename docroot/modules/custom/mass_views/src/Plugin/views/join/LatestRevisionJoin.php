<?php

namespace Drupal\mass_views\Plugin\views\join;

use Drupal\views\Plugin\views\join\JoinPluginBase;

/**
 * Join handler to join an entity data table to its latest revision data.
 *
 * Joins the revision data table (e.g., media_field_revision) to the entity
 * data table (e.g., media_field_data) using the maximum revision ID.
 *
 * Configuration keys (in addition to standard join config):
 * - revision_table: The base revision table (e.g., 'media_revision') used
 *   to determine the max vid.
 * - entity_id_field: The entity ID field name (e.g., 'mid').
 * - revision_id_field: The revision ID field name (e.g., 'vid').
 * - langcode_field: The langcode field name (e.g., 'langcode'). Optional.
 *
 * @ViewsJoin("latest_revision_join")
 */
class LatestRevisionJoin extends JoinPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildJoin($select_query, $table, $view_query) {
    $left = $view_query->getTableInfo($this->leftTable);
    $left_field = "$left[alias].$this->leftField";

    $entity_id_field = $this->configuration['entity_id_field'] ?? 'mid';
    $revision_id_field = $this->configuration['revision_id_field'] ?? 'vid';
    $revision_table = $this->configuration['revision_table'] ?? 'media_revision';
    $langcode_field = $this->configuration['langcode_field'] ?? NULL;

    // Primary join condition: match entity ID.
    $condition = "$left_field = $table[alias].$this->field";

    // Subquery to get the latest revision ID per entity.
    $subquery = $select_query->getConnection()->select($revision_table, 'mr_latest');
    $subquery->addExpression("MAX(mr_latest.$revision_id_field)", $revision_id_field);
    $subquery->where("mr_latest.$entity_id_field = $left[alias].$entity_id_field");

    // Constrain to the latest revision.
    $condition .= " AND $table[alias].$revision_id_field = ($subquery)";

    // For translatable entities, also match langcode.
    if ($langcode_field) {
      $condition .= " AND $table[alias].$langcode_field = $left[alias].$langcode_field";
    }

    $select_query->addJoin($this->type, $this->table, $table['alias'], $condition);
  }

}