<?php
namespace Drupal\mass_content\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\node\NodeInterface;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "update_redirects"
 * )
 */
class UpdateRedirects extends SqlBase {

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
   * Gets all redirects for all services pages and yields them as per
   * https://www.drupal.org/project/drupal/issues/3017237
   *
   * @return \Generator
   */
  protected function initializeIterator() {
    $rows = [];
    $result = $this->prepareQuery()->execute();
    while ($query_row = $result->fetchAssoc()) {
      $source_nid = $query_row['nid'];
      $new_nid = \Drupal::service('migrate.lookup')->lookup('service_details', ['nid' => $source_nid])[0]['nid'];
      $redirects = \Drupal::service('redirect.repository')->findByDestinationUri(["internal:/node/$source_nid", "entity:node/$source_nid"]);
      foreach ($redirects as $redirect) {
        $rows[] = ['rid' => $redirect->id(), 'uri' => "entity:node/$new_nid"];
      }
    }

    foreach ($rows as $row) {
      yield $row;
    }
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
      'rid' => [
        'type' => 'integer',
        'alias' => 'r',
      ],
    ];
  }
}
