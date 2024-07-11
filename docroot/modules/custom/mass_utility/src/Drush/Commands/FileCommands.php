<?php

namespace Drupal\mass_utility\Drush\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Developer tool to check on file usage.
 *
 * This class runs a pretty comprehensive scan of content for references to
 * files. You can use it to check the impact of file deletion in a reliable way.
 *
 * Check queries:
 * SELECT COUNT(*), entity_type, type FROM file_usage_real GROUP BY entity_type, type;
 * SELECT COUNT(*), type, module FROM file_usage GROUP BY type, module;
 *
 * @todo Link fields are not normalized.
 */
final class FileCommands extends DrushCommands {

  use AutowireTrait;

  private $batch = [];

  private $tableName = 'file_usage_real';

  private $schema = [
    'fields' => [
      'fid' => ['type' => 'int', 'not null' => TRUE],
      'entity_type' => [
        'type' => 'varchar',
        'not null' => TRUE,
        'length' => 128,
      ],
      'entity_id' => ['type' => 'int', 'not null' => TRUE],
      'type' => ['type' => 'varchar', 'not null' => TRUE, 'length' => 128],
    ],
    'indexes' => [
      'fid' => ['fid'],
    ],
  ];

  public function __construct(protected Connection $database, protected EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct();
  }

  /**
   * Launchpoint for the drush command.
   *
   * @command ma:check-file-usage
   */
  public function checkUsage() {
    $this->ensureTable();
    // Get all fields that can possibly reference files.
    // Gather the files that they ARE using.
    // Compare it with the files that are SAID to be in use.
    $files = $this->getReferencesFromFields();

    foreach ($files as $file) {
      $this->batchWrite($file);
    }
    $this->doBatch();
  }

  /**
   * Queues records for writing in chunks.
   *
   * @param array $record
   *   An array representing data for a single row.
   *
   * @throws \Exception
   */
  private function batchWrite(array $record) {
    $this->batch[] = $record;
    if (count($this->batch) > 500) {
      $this->doBatch();
    }
  }

  /**
   * Writes a batch of records to the file_usage_real table.
   *
   * @throws \Exception
   */
  private function doBatch() {
    $query = $this->database->insert($this->tableName);
    $query->fields(array_keys($this->schema['fields']));
    while ($record = array_pop($this->batch)) {
      $query->values($record);
    }
    $query->execute();
  }

  /**
   * Yields arrays with details of referenced files from all relevant fields.
   *
   * @return \Generator
   *   Array from etReferencedFilesFromEntityReferenceField().
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  private function getReferencesFromFields() {
    $fieldStorage = $this->manager->getStorage('field_config');

    /** @var \Drupal\field\Entity\FieldStorageConfig $field */
    foreach ($fieldStorage->loadMultiple() as $field) {
      switch ($field->getType()) {
        case 'file':
        case 'image':
          yield from $this->getReferencedFilesFromEntityReferenceField($field, 'file');
          break;

        case 'link':
          yield from $this->getReferencedFilesFromLinkField($field);
        case 'entity_reference':
        case 'entity_reference_revisions':
          yield from $this->getReferencedFilesFromEntityReferenceField($field, 'entity');
          // This is a field we care about.
          break;

        case 'text':
        case 'text_long':
        case 'text_with_summary':
          yield from $this->getReferencedFilesFromTextField($field);
          break;

        case 'string':
        case 'boolean':
        case 'geofield':
        case 'datetime':
        case 'string_long':
        case 'list_string':
        case 'metatag':
        case 'google_map_field':
        case 'address':
        case 'email':
        case 'telephone':
        case 'integer':
        case 'form_embed':
        case 'list_integer':
        case 'daterange':
        case 'key_value':
        case 'office_hours':
        case 'video_embed_field':
        default:
          // These we definitely don't care about.
          break;
      }
    }
    return new \EmptyIterator();
  }

  /**
   * Generate a list of files that are referenced via an entity/file ref field.
   *
   * @param \Drupal\field\Entity\FieldConfig $field
   *   The field instance.
   * @param string $type_name
   *   Name of the entity type to be passed back as 'type'.
   *
   * @return \Generator
   *   The list of records.
   */
  private function getReferencedFilesFromEntityReferenceField(FieldConfig $field, string $type_name) {
    if ($field->getFieldStorageDefinition()->getSetting('target_type') === 'file') {
      // Join the table to file_managed directly.
      $field_table = $this->getFieldTable($field);
      $target_id_column = $this->getFieldColumn($field, 'target_id');
      $query = $this->database->select('file_managed', 'f');
      $query->innerJoin($field_table, 'r', "f.fid = r.{$target_id_column}");
      $query->fields('f', ['fid']);
      $query->fields('r', ['entity_id']);

      foreach ($query->execute() as $row) {
        yield [
          'entity_type' => $field->getTargetEntityTypeId(),
          'entity_id' => $row->entity_id,
          'type' => $type_name,
          'fid' => $row->fid,
        ];
      }
      return new \EmptyIterator();
    }

    // For all other types, we don't care.
    return new \EmptyIterator();
  }

  /**
   * Generate a list of files that are directly linked to via a link field.
   *
   * @param \Drupal\field\FieldConfigInterface $field
   *   The field instance.
   *
   * @return \Generator
   *   The list of records.
   */
  private function getReferencedFilesFromLinkField(FieldConfigInterface $field) {
    $field_table = $this->getFieldTable($field);
    $uri_column = $this->getFieldColumn($field, 'uri');

    // Use a subquery to extract all the links to files from this table, then
    // join it to the file_managed table. This is an order of magnitude more
    // performant than a JOIN on CONCAT().
    $subquery = $this->database->select($field_table, 'r');
    $subquery->condition($uri_column, 'entity:file/%', 'LIKE');
    $subquery->addExpression('SUBSTRING(r.' . $uri_column . ', 13)', 'fid');
    $subquery->addField('r', 'entity_id');

    $query = $this->database->select('file_managed', 'f');
    $query->innerJoin($subquery, 'r', 'f.fid = r.fid');
    $query->addField('r', 'entity_id');
    $query->addField('f', 'fid');

    foreach ($query->execute() as $row) {
      yield [
        'entity_type' => $field->getTargetEntityTypeId(),
        'entity_id' => $row->entity_id,
        'type' => 'link',
        'fid' => $row->fid,
      ];
    }
    return new \EmptyIterator();
  }

  /**
   * Generate a list of files embedded in a full text field.
   *
   * @param \Drupal\field\FieldConfigInterface $field
   *   The field instance.
   *
   * @return \Generator
   *   The list of records.
   */
  private function getReferencedFilesFromTextField(FieldConfigInterface $field) {
    $field_table = $this->getFieldTable($field);
    $value_column = $this->getFieldColumn($field, 'value');
    $query = $this->database->select($field_table, 'f');

    // Select all the records that have an entity file embed, then loop over all
    // records, extracting entity UUID and making sure it's valid.
    $query->condition($value_column, '%data-entity-type="file"%', 'LIKE');
    $query->addField('f', 'entity_id');
    $query->addField('f', $value_column, 'value');

    $rows = [];
    foreach ($query->execute() as $row) {
      preg_match_all('/data-entity-type="file" data-entity-uuid="([a-z0-9\-]+)"/', $row->value, $matches);
      foreach ($matches[1] as $uuid) {
        // Make sure we capture and register every usage of the file.  It might
        // have more than one in this dataset.
        $rows[$uuid][] = [
          'entity_type' => $field->getTargetEntityTypeId(),
          'entity_id' => $row->entity_id,
          'type' => 'editor',
        ];
      }
    }
    if ($rows) {
      $fids = $this->getFidsForUuids(array_keys($rows));
      foreach ($fids as $uuid => $fid) {
        foreach ($rows[$uuid] as $row) {
          yield $row + ['fid' => $fid];
        }
      }
    }
    return new \EmptyIterator();
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
    $storage = $this->manager->getStorage($field->getTargetEntityTypeId());
    if ($storage instanceof SqlContentEntityStorage) {
      $mapping = $storage->getTableMapping();
      $entity_type = $this->manager->getDefinition($field->getTargetEntityTypeId());
      if ($entity_type->isRevisionable()) {
        return $mapping->getDedicatedRevisionTableName($field->getFieldStorageDefinition());
      }
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
    $storage = $this->manager->getStorage($field->getTargetEntityTypeId());
    if ($storage instanceof SqlContentEntityStorage) {
      $mapping = $storage->getTableMapping();
      return $mapping->getFieldColumnName($field->getFieldStorageDefinition(), $column);
    }
  }

  /**
   * Reset the usage table.
   */
  private function ensureTable() {
    $schema = $this->database->schema();
    if ($schema->tableExists($this->tableName)) {
      $schema->dropTable($this->tableName);
    }
    $schema->createTable($this->tableName, $this->schema);
  }

  /**
   * Given an array of UUIDs, return a keyed array of UUID => fid.
   *
   * @param array $uuids
   *   List of entity uuids.
   *
   * @return array
   *   Array of uuid => fid.
   */
  private function getFidsForUuids(array $uuids) {
    $query = $this->database->select('file_managed', 'f');
    $query->condition('uuid', $uuids, 'IN');
    $query->fields('f', ['uuid', 'fid']);
    return $query->execute()->fetchAllKeyed();
  }

}
