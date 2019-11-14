<?php

namespace Drupal\mass_utility\RedirectReplacement;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Executes search/replace on field data in the database.
 */
class FieldSearcher implements LoggerAwareInterface {
  use LoggerAwareTrait;

  private $db;
  private $entityTypeManager;
  private $journal;

  /**
   * Constructor.
   */
  public function __construct(Connection $connection, EntityTypeManagerInterface $manager, LoggerInterface $logger = NULL) {
    $this->db = $connection;
    $this->entityTypeManager = $manager;
    $this->journal = new Journal($this->db);
    $this->setLogger($logger ?: new NullLogger());
  }

  /**
   * Replace strings in the database.
   *
   * Returns a generator.  Each item in the generator is an associative array
   * of replacement counts (how many times earch search string was replaced in
   * a given table).
   *
   * @param \Drupal\mass_utility\RedirectReplacement\StringSearcher $replacer
   *   The replacer that will be invoked for each value.
   */
  public function replace(StringSearcher $replacer) {
    foreach ($this->buildFieldTableInfo() as $tableInfo) {
      // Clone the replacer to reset all counts. We want to accumulate counts
      // per table so we can get regular updates to the replacements table.
      $_replacer = clone $replacer;
      $this->logger->info(t('Replacing in @table', ['@table' => $tableInfo['table']]));
      $this->replaceInTable($tableInfo['table'], $tableInfo['columns'], $tableInfo['id columns'], $tableInfo['type'], $_replacer);

      // Log the replacements to the journal.
      $this->journal->writeReplacementCounts($_replacer->getCounts());
    }
  }

  /**
   * Search for strings in the database.
   *
   * Returns a generator.  Each item in the generator will be a discovered
   * string.
   *
   * @param \Drupal\mass_utility\RedirectReplacement\StringSearcher $replacer
   *   The string replacer that will be used to find links.
   */
  public function search(StringSearcher $replacer) {
    foreach ($this->buildFieldTableInfo() as $tableInfo) {
      $this->logger->info(t('Searching in @table', ['@table' => $tableInfo['table']]));
      yield from $this->searchInTable($tableInfo['table'], $tableInfo['columns'], $tableInfo['id columns'], $tableInfo['type'], $replacer);
    }
  }

  /**
   * Return the redirect replacement journal.
   *
   * The journal contains a log of everything that's been updated.  It can be
   * saved to disk in JSON format at the end of a replacement session to
   * permanently record the changes that were made.
   *
   * @return \Drupal\mass_utility\RedirectReplacement\Journal
   *   The journal.
   */
  public function journal(): Journal {
    return $this->journal;
  }

  /**
   * Execute replacements for a single table.
   */
  private function replaceInTable($table, array $columns, array $idColumns, string $type, StringSearcher $replacer) {
    $select = $this->db->select($table, 't', ['fetch' => \PDO::FETCH_LAZY]);
    $select->fields('t', array_merge($columns, $idColumns));

    foreach ($select->execute() as $row) {
      $updateValues = [];
      foreach ($columns as $column) {
        $value = NULL;
        switch ($type) {
          case 'html':
            $value = $replacer->replaceHtml($row->{$column});
            break;

          case 'link':
            $value = $replacer->replaceUri($row->{$column});
            break;

          default:
            throw new \Exception(sprintf('Invalid type: %s', $type));
        }
        if ($value !== $row->{$column}) {
          $updateValues[$column] = $value;
        }
        unset($value);
      }
      if ($updateValues) {
        $journalIds = [];
        $update = $this->db->update($table);
        $update->fields($updateValues);
        foreach ($idColumns as $column) {
          $idVal = $row->{$column};
          $journalIds[$column] = $idVal;
          $update->condition($column, $idVal);
        }
        $update->execute();
        // Write a journal entry to log that this change happened.
        $col = reset($columns);
        $this->journal->writeEntry($table, $journalIds, $type, $row->{$col}, $updateValues[$col]);
        unset($values);
        unset($update);
        unset($journalIds);
      }
    }
  }

  /**
   * Executes a search in a single table and yields the results.
   */
  private function searchInTable($table, array $columns, array $idColumns, string $type, StringSearcher $replacer) {
    $select = $this->db->select($table, 't', ['fetch' => \PDO::FETCH_LAZY]);
    $select->fields('t', array_merge($columns, $idColumns));

    foreach ($select->execute() as $row) {
      foreach ($columns as $column) {
        switch ($type) {
          case 'html':
            $ids = [];
            foreach ($idColumns as $col) {
              $ids[$col] = $row->{$col};
            }
            foreach ($replacer->searchHtml($row->{$column}) as $url) {
              yield ['url' => $url, 'table' => $table, 'ids' => $ids];
            }
            break;

          case 'link':
            $ids = [];
            foreach ($idColumns as $col) {
              $ids[$col] = $row->{$col};
            }
            foreach ($replacer->searchText($row->{$column}) as $url) {
              yield ['url' => $url, 'table' => $table, 'ids' => $ids];
            }
            break;

          default:
            throw new \Exception(sprintf('Invalid type: %s', $type));
        }
      }
    }
  }

  /**
   * Return data about all field tables that might contain URLs.
   *
   * This function is a performance hog, so we cache the results heavily.
   *
   * @return array
   *   An array of table info.
   */
  private function buildFieldTableInfo() {
    $info = [];
    $fieldStorage = $this->entityTypeManager->getStorage('field_config');
    foreach ($fieldStorage->loadMultiple() as $field) {
      switch ($field->getType()) {
        case 'link':
          if ($table = $this->getFieldTable($field)) {
            $info[$table] = [
              'table' => $table,
              'columns' => [$this->getFieldColumn($field, 'uri')],
              'id columns' => ['entity_id', 'deleted', 'delta', 'langcode'],
              'type' => 'link',
            ];
          }
          break;

        case 'text':
        case 'text_long':
        case 'text_with_summary':
          if ($table = $this->getFieldTable($field)) {
            $info[$table] = [
              'table' => $table,
              'columns' => [$this->getFieldColumn($field, 'value')],
              'id columns' => ['entity_id', 'deleted', 'delta', 'langcode'],
              'type' => 'html',
            ];
          }
          break;

      }
    }

    return $info;
  }

  /**
   * Return the name of the table we care about for a field.
   *
   * @param \Drupal\field\FieldConfigInterface $field
   *   The field storage definition we care about.
   *
   * @return null|string
   *   The table name.
   */
  private function getFieldTable(FieldConfigInterface $field) {
    /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $storage */
    $storage = $this->entityTypeManager->getStorage($field->getTargetEntityTypeId());
    if ($storage instanceof SqlContentEntityStorage) {
      $mapping = $storage->getTableMapping();
      return $mapping->getFieldTableName($field->getName());
    }
  }

  /**
   * Get the actual name of the column from a field table.
   *
   * @param \Drupal\field\FieldConfigInterface $field
   *   The field config instance.
   * @param string $column
   *   The property name.
   *
   * @return string
   *   The actual column name.
   */
  private function getFieldColumn(FieldConfigInterface $field, string $column) {
    $storage = $this->entityTypeManager->getStorage($field->getTargetEntityTypeId());
    if ($storage instanceof SqlContentEntityStorage) {
      $mapping = $storage->getTableMapping();
      return $mapping->getFieldColumnName($field->getFieldStorageDefinition(), $column);
    }
  }

}
