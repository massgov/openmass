<?php

namespace Drupal\mass_utility\RedirectReplacement;

use Drupal\Core\Database\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Contains code for tracking legacy redirect replacements.
 *
 * This class handles all database interaction for the purposes of building an
 * index of replacements, tracking progress of replacements, etc. It is not
 * responsible for actually doing any replacements though.
 */
class Indexer implements LoggerAwareInterface {
  use LoggerAwareTrait;

  const ONETOONE_TABLE_NAME = 'redirect_replacements_onetoone';
  const CONTENT_TABLE_NAME = 'redirect_replacements_content';
  const CONTENT_DEBUG_TABLE_NAME = 'redirect_replacements_o2o_debug';

  private static $oneToOneSchema = [
    'fields' => [
      'id' => [
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => TRUE,
        'auto_increment' => TRUE,
      ],
      'source' => [
        'type' => 'varchar',
        'length' => 500,
        'not null' => TRUE,
        'binary' => TRUE,
      ],
      'source_hash' => [
        'type' => 'char',
        'length' => 32,
        'not null' => TRUE,
      ],
      'destination' => [
        'type' => 'varchar',
        'not null' => TRUE,
        'length' => 500,
        'binary' => TRUE,
      ],
      'destination_hash' => [
        'type' => 'char',
        'length' => 32,
        'not null' => TRUE,
      ],
    ],
    'unique keys' => [
      'source_hash' => ['source_hash'],
    ],
    'primary key' => ['id'],
  ];

  private static $contentSchema = [
    'fields' => [
      'hash' => [
        'type' => 'char',
        'length' => 32,
        'not null' => TRUE,
      ],
      'url' => [
        'type' => 'varchar',
        'length' => 500,
        'not null' => TRUE,
        'binary' => TRUE,
      ],
      'count' => [
        'type' => 'int',
        'unsigned' => TRUE,
        'default' => 1
      ]
    ],
    'indexes' => [
      'url' => ['url']
    ],
    'primary key' => ['hash'],
  ];
  private static $contentDebugSchema = [
    'fields' => [
      'hash' => [
        'type' => 'char',
        'length' => 32,
        'not null' => TRUE,
      ],
      'table_name' => [
        'type' => 'varchar',
        'length' => 128,
        'not null' => TRUE,
      ],
      'ids' => [
        'type' => 'varchar',
        'length' => 256,
        'not null' => TRUE,
      ],
    ],
    'indexes' => [
      'hash' => ['hash']
    ],
  ];

  private $database;

  /**
   * Constructor.
   */
  public function __construct(Connection $database, LoggerInterface $logger = NULL) {
    $this->database = $database;
    $this->setLogger($logger ?: new NullLogger());
  }

  /**
   * Builds up an index of legacy URLs seen in the content.
   *
   * This method just tracks matches that are discovered in content.
   *
   * @see FieldSearcher::search()
   */
  public function buildContentIndex(iterable $discovered) {
    $this->recreateTable(self::CONTENT_TABLE_NAME, self::$contentSchema);
    $this->recreateTable(self::CONTENT_DEBUG_TABLE_NAME, self::$contentDebugSchema);

    foreach (self::iteratorChunk($discovered, 500) as $chunk) {
      $values = [];
      $value_placeholders = [];
      $debug_values = [];
      $debug_placeholders = [];
      $i = 0;
      foreach ($chunk as $record) {
        $value = [
          ':db_placeholder_' . $i++ => md5($record['url']),
          ':db_placeholder_' . $i++ => $record['url']
        ];
        $values += $value;
        $value_placeholders[] = '(' . implode(',', array_keys($value)) . ')';

        $debug_value = [
          ':db_placeholder_' . $i++ => md5($record['url']),
          ':db_placeholder_' . $i++ => $record['table'],
          ':db_placeholder_' . $i++ => json_encode($record['ids'])
        ];
        $debug_values += $debug_value;
        $debug_placeholders[] = '(' . implode(',', array_keys($debug_value)) . ')';
      }

      $value_string = implode(', ', $value_placeholders);
      $query = "INSERT INTO " . self::CONTENT_TABLE_NAME . "(hash, url) VALUES {$value_string} ON DUPLICATE KEY UPDATE count = count +1";
      $stmt = $this->database->prepareQuery($query);
      $stmt->execute($values);

      $debug_value_string = implode(', ', $debug_placeholders);
      $query = "INSERT INTO " . self::CONTENT_DEBUG_TABLE_NAME . "(hash, table_name, ids) VALUES {$debug_value_string}";
      $stmt = $this->database->prepareQuery($query);
      $stmt->execute($debug_values);
    }
  }

  /**
   * Builds up an index of 1-1 redirects (legacy + document).
   */
  public function buildOneToOneIndex(iterable $one_to_ones) {
    $destination = self::ONETOONE_TABLE_NAME;
    $this->recreateTable($destination, self::$oneToOneSchema);
    foreach (self::iteratorChunk($one_to_ones, 2000) as $chunk) {
      $i = 0;
      $valuePlaceholders = [];
      $values = [];
      foreach ($chunk as $item) {
        $value = [
          ':db_placeholder_' . $i++ => $item['source'],
          ':db_placeholder_' . $i++ => $item['destination'],
          ':db_placeholder_' . $i++ => md5($item['source']),
          ':db_placeholder_' . $i++ => md5($item['destination']),
        ];
        $values += $value;
        $valuePlaceholders[] = '(' . implode(',', array_keys($value)) . ')';
      }
      $valuePlaceholderString = implode(',', $valuePlaceholders);
      $query = "INSERT INTO {$destination} (source, destination, source_hash, destination_hash) VALUES {$valuePlaceholderString} ON DUPLICATE KEY UPDATE source = source";
      $stmt = $this->database->prepareQuery($query);
      $stmt->execute($values);
    }

    // Once the index is fully built, attempt to detect and resolve redirect
    // chains.  A redirect chain is when a redirect "a" has a destination that
    // matches the source of another redirect "b".  We resolve chains by
    // setting the destination of "a" to point directly to the destination of
    // "b". This requires multiple passes to complete.
    do {
      $chainedQuery = $this->database->select(self::ONETOONE_TABLE_NAME, 'a');
      $chainedQuery->innerJoin(self::ONETOONE_TABLE_NAME, 'b', 'a.destination_hash = b.source_hash AND b.source_hash != b.destination_hash');
      $chainedQuery->fields('a', ['id']);
      $chainedQuery->fields('b', ['destination', 'destination_hash']);
      $has_chained = FALSE;
      $unchained = 0;
      foreach ($chainedQuery->execute() as $chain) {
        $has_chained = TRUE;
        $this->database->update(self::ONETOONE_TABLE_NAME)
          ->fields([
            'destination' => $chain->destination,
            'destination_hash' => $chain->destination_hash
          ])
          ->condition('id', $chain->id)
          ->execute();
        $unchained++;
      }
      $this->logger->debug(sprintf('Completed chained redirects pass - %d fixed', $unchained));
    } while ($has_chained);
  }

  /**
   * Fetch an associative array of the replacement strings.
   *
   * @return array
   *   An associative array of replacements, keyed by source.
   */
  public function getSafeReplacements(): array {
    $select = $this->database->select(self::ONETOONE_TABLE_NAME, 't');
    $select->innerJoin(self::CONTENT_TABLE_NAME, 's', 't.source_hash = s.hash');
    $select->orderBy('source', 'DESC');
    $select->fields('t', ['source', 'destination']);

    return $select->execute()->fetchAllKeyed();
  }

  /**
   * Drop/Create a table from a schema definition.
   *
   * @param string $name
   *   The name of the table.
   * @param array $definition
   *   The schema definition.
   */
  private function recreateTable(string $name, array $definition) {
    $schema = $this->database->schema();
    if ($schema->tableExists($name)) {
      $schema->dropTable($name);
    }
    $schema->createTable($name, $definition);
  }

  /**
   * Batch process an iterable by using a generator.
   *
   * When you pass this function a iterable object of individual items, it will
   * batch the items into chunks.  This is similar to PHP's array_chunk, except
   * that it works on any iterators (database, generators, etc).
   *
   * @param iterable $iterable
   *   The iterable object.
   * @param int $size
   *   The number of items to include in each chunk.
   */
  private static function iteratorChunk(iterable $iterable, int $size) {
    $chunk = [];
    foreach ($iterable as $item) {
      $chunk[] = $item;
      if (count($chunk) >= $size) {
        yield $chunk;
        $chunk = [];
      }
    }
    // Yield the last chunk.
    if ($chunk) {
      yield $chunk;
    }
  }

}
