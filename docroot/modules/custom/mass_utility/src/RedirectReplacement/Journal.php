<?php

namespace Drupal\mass_utility\RedirectReplacement;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseExceptionWrapper;

/**
 * This class captures info about replacements that are made.
 *
 * This info is captured for debugging, and to protect us in the event that we
 * need to roll some values back to their original states.  There is no rollback
 * mechanism built yet, but the file is just JSON, and could easily be parsed
 * to extract the data we need.
 */
class Journal {
  const JOURNAL_TABLE = 'redirect_replacements_journal';
  const COUNT_TABLE = 'redirect_replacements_counts';

  private static $journalSchema = [
    'fields' => [
      'time' => [
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => TRUE,
      ],
      'table_name' => [
        'type' => 'varchar',
        'length' => 128,
        'not null' => TRUE,
      ],
      'ids' => [
        'type' => 'varchar',
        'length' => 128,
        'not null' => TRUE,
      ],
      'type' => [
        'type' => 'varchar',
        'length' => 32,
        'not null' => TRUE,
      ],
      'old' => [
        'type' => 'blob',
        'size' => 'big',
      ],
      'new' => [
        'type' => 'blob',
        'size' => 'big',
      ],
    ],
    'primary key' => ['time', 'table_name', 'ids']
  ];

  private static $countSchema = [
    'fields' => [
      'hash' => [
        'type' => 'char',
        'length' => 32,
        'not null' => TRUE,
      ],
      'source' => [
        'type' => 'varchar',
        'length' => 500,
        'not null' => TRUE,
        'binary' => TRUE,
      ],
      'replaced' => [
        'type' => 'int',
        'unsigned' => TRUE,
        'default' => 0,
      ],
    ],
    'primary key' => ['hash'],
  ];

  private $db;

  /**
   * Constructor.
   */
  public function __construct(Connection $db) {
    $this->db = $db;
  }

  /**
   * Reset (empty) the journal.
   */
  public function reset() {
    $schema = $this->db->schema();
    if ($schema->tableExists(self::JOURNAL_TABLE)) {
      $schema->dropTable(self::JOURNAL_TABLE);
    }
    if ($schema->tableExists(self::COUNT_TABLE)) {
      $schema->dropTable(self::COUNT_TABLE);
    }
  }

  /**
   * Ensure that a table exists.
   *
   * @param string $name
   *   The table name.
   * @param array $definition
   *   The schema definition.
   *
   * @return bool
   *   A flag indicating whether the table was created or not.
   */
  private function ensureTable(string $name, array $definition) {
    $schema = $this->db->schema();
    if (!$schema->tableExists($name)) {
      $schema->createTable($name, $definition);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Write a journal entry describing a change.
   *
   * @param string $table
   *   The name of the table that was affected.
   * @param array $ids
   *   The primary keys of the record that was affected.
   * @param string $type
   *   The type of transformation that was performed.
   * @param string $old
   *   The old value.
   * @param string $new
   *   The new value.
   *
   * @throws \Exception
   */
  public function writeEntry(string $table, array $ids, string $type, string $old, string $new) {
    try {
      $this->db->insert(self::JOURNAL_TABLE)
        ->fields([
          'time' => time(),
          'table_name' => $table,
          'ids' => json_encode($ids),
          'type' => $type,
          'old' => $old,
          'new' => $new
        ])
        ->execute();
    }
    // Create the table on the fly if it doesn't already exist.
    catch (DatabaseExceptionWrapper $e) {
      if ($this->ensureTable(self::JOURNAL_TABLE, self::$journalSchema)) {
        return $this->writeEntry($table, $ids, $type, $old, $new);
      }
      else {
        throw $e;
      }
    }
  }

  /**
   * Note counts of replacement source strings.
   *
   * @param array $counts
   *   An associative array of counts, keyed on source string.
   */
  public function writeReplacementCounts(array $counts) {
    try {
      foreach ($counts as $source => $count) {
        $this->db->merge(self::COUNT_TABLE)
          ->insertFields([
            'source' => $source,
            'replaced' => $count,
          ])
          ->keys(['hash' => md5($source)])
          ->expression('replaced', 'replaced + :count', [':count' => $count])
          ->execute();
      }
    }
    // Create the table on the fly.
    catch (DatabaseExceptionWrapper $e) {
      if ($this->ensureTable(self::COUNT_TABLE, self::$countSchema)) {
        return $this->writeReplacementCounts($counts);
      }
      else {
        throw $e;
      }
    }
  }

  /**
   * Save the journal as a JSON file.
   *
   * @param string $filepath
   *   The path to write data to.
   */
  public function flush(string $filepath) {
    $select = $this->db->select(self::JOURNAL_TABLE, 'j');
    $select->fields('j');
    $handle = fopen($filepath, 'w');
    fwrite($handle, "[\n");
    foreach ($select->execute() as $row) {
      $prefix = isset($prefix) ? ",\n" : '';
      fwrite($handle, $prefix . json_encode($row));
    }
    fwrite($handle, "\n]");
    fclose($handle);
  }

}
