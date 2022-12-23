<?php

namespace Drupal\mass_content\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\mass_content\Field\FieldType\DynamicLinkItem;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;
use Drupal\node\Entity\Node;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "update_references"
 * )
 */
class UpdateReferences extends SqlBase {

  /**
   * Get all the non-service details pages that reference a service_detail page.
   */
  public function query(): SelectInterface {
    $query = $this->select('entity_usage', 'eu')
      ->fields('eu', ['source_id'])
      ->condition('eu.target_type', 'node')
      ->groupBy('eu.source_id');
    $query->innerJoin('migrate_map_service_details', 'mmsd', 'eu.target_id=mmsd.sourceid1');
    // Limit to just the most revision in the entity_usage table
    $query->innerJoin('node', 'n', 'eu.target_id=n.nid');
    $query->addExpression('COUNT(eu.source_id)', 'count');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'source_id' => [
        'type' => 'integer',
        'alias' => 'eu',
      ],
    ];
  }

  public function prepareRow(Row $row) {
    $changed = FALSE;
    /** @var \Drupal\mass_content\Entity\Bundle\node\NodeBundle $node */
    $node = Node::load($row->getSourceProperty('source_id'));
    // Get all the fields that we need to change in this sourceid.
    $query = $this->select('entity_usage', 'eu')
      ->fields('eu', ['method', 'field_name'])
      ->fields('mmsd', ['sourceid1'])
      ->condition('eu.target_type', 'node')
      ->condition('eu.source_id', $row->getSourceProperty('source_id'))
      ->condition('eu.source_vid', $node->getLoadedRevisionId());
    $query->addField('eu', 'target_id', 'reference_value_old');
    $query->addField('mmsd', 'destid1', 'reference_value_new');
    $query->addField('n', 'type', 'content_type');
    $query->innerJoin('migrate_map_service_details', 'mmsd', 'eu.target_id=mmsd.sourceid1');
    $query->innerJoin('node', 'n', 'mmsd.sourceid1=n.nid');
    $refs = $query->execute()->fetchAll();
    foreach ($refs as $ref) {
      $field_name = $ref['field_name'];
      $list = $node->get($field_name);
      foreach ($list as $delta => $item) {
        switch (get_class($item)) {
          case DynamicLinkItem::class:
            // Only update the delta that was migrated (when there are multiple values).
            if ($item->get('uri')->getString() == 'entity:node/' . $ref['reference_value_old']) {
              $prop_name = "$field_name/$delta/uri";
              $row->setDestinationProperty($prop_name, 'entity:node/' . $ref['reference_value_new']);
              $changed = TRUE;
            }
            break;
        }
      }
    }
    if (!$changed) {
      // throw new MigrateSkipRowException('No changes', TRUE);
    }
  }

}
