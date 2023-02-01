<?php

namespace Drupal\mass_migrate\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

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
    $query = $this->select('node', 'n')
      ->fields('n', ['nid'])
      ->fields('p', ['alias'])
      ->condition('n.type', 'service_details');
    $query->innerJoin('path_alias', 'p', 'p.id=n.nid');
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
      $row->setDestinationProperty('redirect_source', $row->getSourceProperty('alias'));
      $row->setDestinationProperty('redirect_redirect', 'entity:node/' . $info_nid[0]['nid']);
    }
    else {
      throw new MigrateSkipRowException('No info details nid found', TRUE);
    }
  }

}
