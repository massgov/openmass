<?php

namespace Drupal\mass_migrate\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;
use Drupal\node\NodeInterface;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "flaggings"
 * )
 */
class Flaggings extends SqlBase {

  /**
   * Get all the service details pages.
   */
  public function query(): SelectInterface {
    $query = $this->select('flagging', 'f')
      ->fields('f', ['id', 'uid'])
      ->fields('mmsd', ['destid1'])
      ->condition('nfd.type', 'service_details');
    $query->innerJoin('node_field_data', 'nfd', "nfd.nid=f.entity_id AND f.entity_type='node'");
    $query->innerJoin('migrate_map_service_details', 'mmsd', "mmsd.sourceid1=f.entity_id AND f.entity_type='node'");
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
      'id' => [
        'type' => 'integer',
        'alias' => 'f',
      ],
    ];
  }

}
