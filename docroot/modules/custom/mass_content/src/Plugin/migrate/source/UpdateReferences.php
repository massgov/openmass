<?php

namespace Drupal\mass_content\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
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
    /** @var \Drupal\mass_content\Entity\Bundle\node\NodeBundle $node */
    $node = Node::load($row->getSourceProperty('source_id'));
    // Get all the fields we need to change in this sourceid.
    $query = $this->select('entity_usage', 'eu')
      ->fields('eu', ['method', 'field_name'])
      ->condition('eu.target_type', 'node')
      ->condition('eu.source_id_id', $row->getSourceProperty('source_id'));
    // ->addField('eu', 'source_id', 'source_id')
    $query->addField('eu', 'target_id', 'reference_value_old');
    $query->addField('mmsd', 'destid1', 'reference_value_new');
    $query->innerJoin('migrate_map_service_details', 'mmsd', 'eu.target_id=mmsd.sourceid1');
    $refs = $query->execute()->fetchAllKeyed();
    foreach ($refs as $ref) {
      $a=1;
    }
  }

}
