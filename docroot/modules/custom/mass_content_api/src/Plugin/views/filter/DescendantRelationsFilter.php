<?php

namespace Drupal\mass_content_api\Plugin\views\filter;

use Drupal\Core\Database\Query\Condition;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filters content by link relationships.
 *
 * Parent/child relationships are stored in the descendant_relations table.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("mass_content_api_descendant_relations_filter")
 */
class DescendantRelationsFilter extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $nid_alias = $this->query->ensureTable('node_field_data', $this->relationship);
    // Create a sub query of the descendant_relations table to act as a "not
    // exists()" condition on the main views query.
    $sub_query = \Drupal::database()->select('descendant_relations', 'descendant_relations')
      ->fields('descendant_relations', [])
      ->where("descendant_relations.destination_id = $nid_alias.nid")
      ->condition("descendant_relations.destination_type", "node")
      ->condition("descendant_relations.relationship", "links_to");
    // Add a new "not exists" condition to the views query with the sub_query.
    $this->query->addWhere($this->options['group'], (new Condition('AND'))
      ->notExists($sub_query));
  }

}
