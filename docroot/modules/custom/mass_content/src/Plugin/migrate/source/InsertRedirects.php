<?php
namespace Drupal\mass_content\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
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
      ->condition('n.type', 'service_details')
      ->condition('n.nid', 384431);
    $query->innerJoin('node_field_data', 'nfd', 'nfd.nid=n.nid AND nfd.vid=n.vid');
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
    $info_nid = \Drupal::service('migrate.lookup')->lookup('service_details', ['nid' => $service_nid])[0]['nid'];
    $row->setSourceProperty('redirect_source', "node/$service_nid");
    $row->setSourceProperty('redirect_redirect', "entity:node/$info_nid");
  }

}
