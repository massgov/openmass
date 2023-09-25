<?php

namespace Drupal\mass_migrate\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\migrate\Plugin\migrate\source\SqlBase;

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
      ->fields('f', ['id', 'uid']);
    $query->innerJoin('migrate_map_service_details', 'mmsd', "f.entity_id=mmsd.sourceid1 AND f.entity_type='node'");
    $query->addField('mmsd', 'destid1');
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
