<?php

namespace Drupal\mass_utility\RedirectReplacement;

use Drupal\Core\Database\Connection;

/**
 * Provides generators for 1-1 redirect sources.
 */
class OneToOneProvider {

  private $database;

  /**
   * Constructor.
   */
  public function __construct(Connection $connection) {
    $this->database = $connection;
  }

  /**
   * Combine multiple generators into a single iterator.
   *
   * @param array $iterators
   *   The generators you want to pipe data from.
   */
  public static function combine(array $iterators) {
    foreach ($iterators as $iterator) {
      yield from $iterator;
    }
  }

  /**
   * Get replacements for legacy redirects.
   */
  public function getLegacyRedirects() {
    // Query for legacy redirects.
    $query = $this->database->select('node_field_data', 'n');
    $query->innerJoin('node__field_legacy_redirects_ref_conte', 'd', 'n.nid = d.entity_id');
    $query->innerJoin('node__field_legacy_redirects_legacyurl', 's', 'n.nid = s.entity_id');
    $query->innerJoin('node__field_legacy_redirect_env', 'e', 'n.nid = e.entity_id AND e.field_legacy_redirect_env_value = 0');
    $query->addField('s', 'field_legacy_redirects_legacyurl_value', 'source');
    $query->addField('d', 'field_legacy_redirects_ref_conte_target_id', 'nid');
    $query->condition('n.status', 1);
    $query->orderBy('source', 'DESC');
    foreach ($query->execute() as $row) {
      $src = str_replace('http://www.mass.gov', '', $row->source);
      yield [
        'source' => $src,
        'destination' => '/node/' . $row->nid,
      ];
      // If the legacy path ends with a slash, also return a version that does
      // not have the trailing slash. This matches logic we have in place on
      // the legacy apache servers.
      if (substr($src, -1) === '/') {
        $deslashed = substr($src, 0, -1);
        yield [
          'source' => $deslashed,
          'destination' => '/node/' . $row->nid,
        ];
      }
    }
  }

  /**
   * Get replacements for the legacy docs migration.
   */
  public function getDocumentRedirects() {
    $domain_length = strlen('https://www.mass.gov');
    $handle = fopen(drupal_get_path('module', 'mass_utility') . '/data/legacy_docs_redirects.txt', 'r');
    while (!feof($handle)) {
      $line = trim(fgets($handle));
      if (empty($line)) {
        continue;
      }
      $parts = explode(' ', $line);
      yield [
        'source' => $parts[0],
        'destination' => substr($parts[1], $domain_length),
      ];
    }
  }

  /**
   * Get replacements for Drupal media.
   */
  public function getDrupalMediaPaths() {
    $select = $this->database->select('media', 'm');
    $select->leftJoin('path_alias', 'pa', "pa.path = CONCAT('/media/', m.mid)");
    $select->addField('m', 'mid');
    $select->addField('pa', 'alias');
    foreach ($select->execute() as $row) {
      yield [
        'source' => '/media/' . $row->mid,
        'destination' => '/media/' . $row->mid,
      ];
      yield [
        'source' => '/media/' . $row->mid . '/download',
        'destination' => '/media/' . $row->mid . '/download',
      ];
      // Also replace the aliased path.
      if ($row->alias) {
        yield [
          'source' => $row->alias,
          'destination' => '/media/' . $row->mid,
        ];
      }
    }
  }

  /**
   * Get replacements for Drupal nodes.
   */
  public function getDrupalNodePaths() {
    $select = $this->database->select('node', 'n');
    $select->innerJoin('path_alias', 'pa', "pa.path = CONCAT('/node/', n.nid)");
    $select->addField('pa', 'alias');
    $select->addField('n', 'nid');

    foreach ($select->execute() as $row) {
      yield [
        'source' => '/node/' . $row->nid,
        'destination' => '/node/' . $row->nid,
      ];
      yield [
        'source' => $row->alias,
        'destination' => '/node/' . $row->nid,
      ];
    }
  }

  /**
   * Get replacements for Drupal location pages.
   */
  public function getDrupalLocationPages() {
    $select = $this->database->select('node', 'n');
    $select->innerJoin('path_alias', 'pa', "pa.path = CONCAT('/node/', n.nid)");
    $select->addField('pa', 'alias');
    $select->addField('n', 'nid');
    $select->condition('type', 'org_page');

    foreach ($select->execute() as $row) {
      yield [
        'source' => $row->alias . '/locations',
        'destination' => '/node/' . $row->nid . '/locations',
      ];
    }
  }

  /**
   * Get replacements for Drupal redirects.
   */
  public function getRedirectPaths() {
    $select = $this->database->select('redirect', 'r');
    $select->addField('r', 'redirect_source__path', 'source');
    $select->addField('r', 'redirect_redirect__uri', 'dest');
    foreach ($select->execute() as $row) {
      $dest = $row->dest;
      if (strpos($dest, 'internal:') === 0) {
        $dest = substr($dest, strlen('internal:'));
      }
      elseif (strpos($dest, 'entity:node/') === 0) {
        $dest = str_replace('entity:', '/', $dest);
      }
      elseif (strpos($dest, 'entity:media/') === 0) {
        $dest = str_replace('entity:', '/', $dest);
      }
      else {
        // If this redirect points to an internal destination, make it relative
        // so we can safely replace it.
        $dest = preg_replace('/^http(s)?:\/\/((www|edit|pilot)\.)?mass\.gov/', '', $dest);
      }
      yield [
        'source' => '/' . $row->source,
        'destination' => $dest,
      ];
    }
  }

  /**
   * Get replacements for Drupal files.
   */
  public function getFilePaths() {
    $chars = [
      ' ' => '%20',
      '$' => '%24',
      '%' => '%25',
      '&' => '%26',
      "'" => '%27',
      "(" => '%28',
      ")" => '%29',
      ',' => '%2C',
      '[' => '%5B',
      ']' => '%%D'
    ];
    $select = $this->database->select('file_managed', 'f');
    $select->condition('uri', 'public://%', 'LIKE');
    $select->addField('f', 'uri');
    foreach ($select->execute()->fetchCol() as $uri) {
      // Source and destination are both URL encoded.
      $src = strtr(str_replace('public://', '/files/', $uri), $chars);
      $dest = strtr(str_replace('public://', '/files/', $uri), $chars);
      yield [
        'source' => $src,
        'destination' => $dest,
      ];
    }
  }

}
