<?php

namespace Drupal\mass_migrate\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "insert_redirects"
 * )
 */
class InsertRedirects extends SqlBase {

  /**
   * Get all the service details pages.
   */
  public function query(): SelectInterface {
    $subquery = $this->select('path_alias', 'p')
      ->fields('p', ['path', 'alias']);
    $subquery->addExpression("CASE
    WHEN p.path LIKE '/node/%' THEN SUBSTR(p.path, 7)
    ELSE NULL
  END", 'nid');
    $query = $this->select('node', 'n')
      ->fields('n', ['nid'])
      ->condition('n.type', 'service_details');
    $query->addField('s', 'alias');
    $query->innerJoin($subquery, 's', 's.nid=n.nid');
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
      'nid' => [
        'type' => 'integer',
        'alias' => 'n',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $service_nid = $row->getSourceProperty('nid');
    if ($info_nid = \Drupal::service('migrate.lookup')->lookup('service_details', ['nid' => $service_nid])) {
      $row->setDestinationProperty('redirect_source', preg_replace('/^\//', '', $row->getSourceProperty('alias')));
      $row->setDestinationProperty('redirect_redirect', 'entity:node/' . $info_nid[0]['nid']);
    }
    else {
      throw new MigrateSkipRowException('No info details nid found', TRUE);
    }
  }

}
