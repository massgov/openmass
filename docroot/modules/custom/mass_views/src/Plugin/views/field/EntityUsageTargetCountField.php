<?php

namespace Drupal\mass_views\Plugin\views\field;

use Drupal\Core\Database\Database;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Provides a field to show the count of target_ids for each source_id.
 *
 * @ViewsField("source_id_target_count")
 */
class EntityUsageTargetCountField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $base_table = $this->view->storage->get('base_table');

    // Dynamically generate the field key to access the entity ID (e.g., 'node_field_revision_nid').
    // Dynamic field key to access the node ID.
    $id_field_key = "{$base_table}_nid";

    // Check if the dynamically generated ID field exists in the $values object.
    if (isset($values->{$id_field_key})) {
      $entity_id = $values->{$id_field_key}; // Fetch the entity ID.

      // Query the entity_usage table to count target_id values for this source_id.
      $query = Database::getConnection()->select('entity_usage', 'eu')
        ->condition('eu.source_id', $entity_id)
        ->condition('eu.source_type', 'node')
        ->countQuery()
        ->execute()
        ->fetchField();
      // Return the count or 0 if no results.
      return $query ?? 0;
    }
    // If no ID was found, return 0.
    return 0;
  }

}
