<?php

namespace Drupal\mass_content_api;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\SchemaObjectExistsException;

/**
 * Storage class for the Descendant Manager.
 *
 * Any database interaction the DescendantManager needs to do should be
 * implemented here. This allows us to unit test the storage portion separately.
 *
 * Note: This is a particularly performance-sensitive part of the application.
 * Queries done here need to be indexed and tested to ensure that they are not
 * slow.
 */
class DescendantStorage implements DescendantStorageInterface {

  const TABLE = 'descendant_relations';
  const DEBUG_TABLE = 'descendant_debug';
  const LINKS_TO = 'links_to';
  const IS_PARENT_OF = 'is_parent_of';

  private $database;
  private $table;
  private $debugTable;

  /**
   * If enabling this, please fix bug where debug is a giant text string, too large for its column.
   *
   * @var bool
   */
  private $debug = FALSE;

  /**
   * Constructor.
   */
  public function __construct(Connection $database, $table = self::TABLE, $debugTable = self::DEBUG_TABLE) {
    $this->database = $database;
    $this->table = $table;
    $this->debugTable = $debugTable;
  }

  /**
   * {@inheritdoc}
   */
  public function removeRelationships(string $reporter_type, int $reporter_id): void {
    $this->wrap(function () use ($reporter_type, $reporter_id) {
      $this->database->delete($this->table)
        ->condition('reporter_type', $reporter_type)
        ->condition('reporter_id', $reporter_id)
        ->execute();
    });
  }

  /**
   * {@inheritdoc}
   */
  public function addParentChildRelation(string $reporter_type, int $reporter_id, string $parent_type, int $parent_id, string $child_type, int $child_id): void {
    $record = [
      'reporter_type' => $reporter_type,
      'reporter_id' => $reporter_id,
      'source_type' => $parent_type,
      'source_id' => $parent_id,
      'destination_type' => $child_type,
      'destination_id' => $child_id,
      'relationship' => self::IS_PARENT_OF,
    ];
    $this->wrap(function () use ($record) {
      $this->database->insert($this->table)
        ->fields($record)
        ->execute();
    });
  }

  /**
   * {@inheritdoc}
   */
  public function addLinkingPage(string $reporter_type, int $reporter_id, string $source_type, int $source_id, string $destination_type, int $destination_id): void {
    $record = [
      'reporter_type' => $reporter_type,
      'reporter_id' => $reporter_id,
      'source_type' => $source_type,
      'source_id' => $source_id,
      'destination_type' => $destination_type,
      'destination_id' => $destination_id,
      'relationship' => self::LINKS_TO,
    ];
    $this->wrap(function () use ($record) {
      $this->database->insert($this->table)
        ->fields($record)
        ->execute();
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getChildren(array $parentIds): array {
    $query = $this->database->select($this->table, 'rel');
    $query->innerJoin('node_field_data', 'n', "rel.destination_type = 'node' AND rel.destination_id = n.nid");
    $query->addField('rel', 'destination_id', 'id');
    $query->addField('rel', 'source_id', 'parent');
    $query->addField('n', 'type', 'type');
    $query->condition('rel.source_id', $parentIds, 'IN');
    $query->condition('rel.source_type', 'node');
    $query->condition('n.status', 1);
    $query->condition('rel.relationship', self::IS_PARENT_OF);

    return $query->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
  }

  /**
   * {@inheritdoc}
   */
  public function getParents(array $childIds): array {
    $query = $this->database->select($this->table, 'rel');
    $query->innerJoin('node_field_data', 'n', "rel.source_type = 'node' AND rel.source_id = n.nid");
    $query->addField('rel', 'source_id', 'id');
    $query->addField('rel', 'destination_id', 'child');
    $query->addField('n', 'type', 'type');
    $query->condition('rel.destination_id', $childIds, 'IN');
    $query->condition('rel.destination_type', 'node');
    $query->condition('n.status', 1);
    $query->condition('rel.relationship', self::IS_PARENT_OF);

    return $query->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
  }

  /**
   * {@inheritdoc}
   */
  public function getLinksTo(string $entity_type, int $entity_id): array {
    $query = $this->database->select($this->table, 'rel');
    $query->addField('rel', 'source_id');
    $query->condition('destination_type', $entity_type);
    $query->condition('destination_id', $entity_id);
    $query->condition('relationship', self::LINKS_TO);

    return $query->execute()->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function addDebug(string $reporter_type, int $reporter_id, float $time, $debug): void {
    if ($this->debug) {
      $record = [
        'reporter_type' => $reporter_type,
        'reporter_id' => $reporter_id,
        'time' => $time,
        'debug' => serialize($debug),
      ];
      $this->wrap(function () use ($record) {
        // Merge query allows us to write over existing records, saving a
        // DELETE, which can be expensive.
        $this->database->merge($this->debugTable)
          ->fields($record)
          ->key([
            'reporter_type' => $record['reporter_type'],
            'reporter_id' => $record['reporter_id'],
          ])
          ->execute();
      }, $this->debugTable);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeDebug(string $reporter_type, int $reporter_id): void {
    if ($this->debug) {
      $this->wrap(function () use ($reporter_type, $reporter_id) {
        $this->database->delete($this->debugTable)
          ->condition('reporter_type', $reporter_type)
          ->condition('reporter_id', $reporter_id)
          ->execute();
      }, $this->debugTable);
    }
  }

  /**
   * Wrap a transaction in a try/catch to create the table on the fly if needed.
   *
   * @param callable $fn
   *   The function to invoke.
   * @param string $table
   *   The name key of the table.  Must be in self::schemaDefinition().
   *
   * @throws \Exception
   */
  private function wrap(callable $fn, string $table = NULL) {
    $try_again = FALSE;
    try {
      $fn();
    }
    catch (\Exception $e) {
      if (!$try_again = $this->ensureTable($table)) {
        throw $e;
      }
    }
    if ($try_again) {
      $fn();
    }
  }

  /**
   * Create the database table if it doesn't exist.
   */
  private function ensureTable(string $table = NULL): bool {
    try {
      $database_schema = $this->database->schema();
      $table = $table ?? $this->table;
      if (!$database_schema->tableExists($table)) {
        $def = $this->schemaDefinition($table);
        if (!$def) {
          throw new \Exception('Unknown table: ' . $table);
        }
        $database_schema->createTable($table, $def);
        return TRUE;
      }
    }
    // If another process has already created the cache table, attempting to
    // recreate it will throw an exception. In this case just catch the
    // exception and do nothing.
    catch (SchemaObjectExistsException $e) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Return the schema definition for the index table.
   */
  private function schemaDefinition($table) {
    $schema = [
      $this->table => [
        'description' => 'Storage for the Descendant Manager',
        'fields' => [
          'reporter_type' => [
            'type' => 'varchar_ascii',
            'length' => 64,
            'not null' => TRUE,
            'default' => '',
            'binary' => TRUE,
            'description' => 'The type of the reporting entity',
          ],
          'reporter_id' => [
            'type' => 'int',
            'not null' => TRUE,
            'description' => 'The id of the entity that owns this record.',
          ],
          'source_type' => [
            'type' => 'varchar_ascii',
            'length' => 64,
            'not null' => TRUE,
            'default' => '',
            'binary' => TRUE,
            'description' => 'The type of the source entity',
          ],
          'source_id' => [
            'type' => 'int',
            'not null' => TRUE,
            'description' => 'The id of the entity that that serves as the source of this relationship',
          ],
          'relationship' => [
            'type' => 'varchar_ascii',
            'length' => 64,
            'not null' => TRUE,
            'default' => '',
            'binary' => TRUE,
          ],
          'destination_type' => [
            'type' => 'varchar_ascii',
            'length' => 64,
            'not null' => TRUE,
            'default' => '',
            'binary' => TRUE,
            'description' => 'The type of the destination entity',
          ],
          'destination_id' => [
            'type' => 'int',
            'not null' => TRUE,
            'description' => 'The id of the entity that that serves as the destination of this relationship',
          ],
        ],
        'indexes' => [
          'reporter' => ['reporter_type', 'reporter_id'],
          'source' => ['source_type', 'source_id', 'relationship'],
          'destination' => [
            'destination_type',
            'destination_id',
            'relationship',
          ],
        ],
      ],
        // Debug table is only used in development.
      $this->debugTable => [
        'description' => 'Debug information for the descendant manager',
        'fields' => [
          'reporter_type' => [
            'type' => 'varchar_ascii',
            'length' => 64,
            'not null' => TRUE,
            'default' => '',
            'binary' => TRUE,
            'description' => 'The type of the reporting entity',
          ],
          'reporter_id' => [
            'type' => 'int',
            'not null' => TRUE,
            'description' => 'The id of the entity that owns this record.',
          ],
          'time' => [
            'type' => 'float',
            'not null' => TRUE,
            'description' => 'The id of the entity that owns this record.',
          ],
          'debug' => [
            'type' => 'blob',
            'not null' => FALSE,
            'description' => 'The extracted data for this node',
          ],
        ],
        'primary key' => [
          'reporter_type', 'reporter_id',
        ],
      ],
    ];
    return $schema[$table];
  }

}
