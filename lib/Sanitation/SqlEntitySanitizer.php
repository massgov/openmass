<?php

namespace MassGov\Sanitation;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Psr\Log\LoggerInterface;

/**
 * Performs sanitization operations for a single entity type.
 *
 * This class requires that the entity type use SQL storage.
 */
class SqlEntitySanitizer {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Entity Storage instance.
   *
   * @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface
   */
  protected $storage;

  /**
   * An array of field storage definitions for this entity type.
   *
   * @var \Drupal\Core\Field\FieldStorageDefinitionInterface[]
   */
  protected $fieldStorageDefinitions = [];

  /**
   * Any logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(Connection $database, SqlEntityStorageInterface $storage, array $fieldStorageDefinitions = [], LoggerInterface $logger) {
    $this->database = $database;
    $this->storage = $storage;
    $this->fieldStorageDefinitions = $fieldStorageDefinitions;
    $this->logger = $logger;
  }

  /**
   * Perform sanitizations.
   */
  public function sanitize() {
    $this->deleteUnpublished();
    $this->deleteRevisions();
    $this->sanitizeRevisionLogs();

    // @todo: It would be nice to clean up url aliases for what we've deleted.
    // I attempted to do that, but couldn't make it performant. Since it doesn't
    // seem like a security issue, we'll skip for now and come back if it
    // becomes an issue.
  }

  /**
   * This method deletes all entities that are currently unpublished.
   *
   * Note: This will not affect unpublished revisions of content that is
   * currently published.
   */
  private function deleteUnpublished() {
    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $mapping */
    $mapping = $this->storage->getTableMapping();
    $type = $this->storage->getEntityType();

    // Skip this step if the entity type doesn't actually have a published
    // status.
    if (!$type->entityClassImplements(EntityPublishedInterface::class)) {
      return;
    }

    // Determine the name of the table that contains the published/status col.
    $dataTable = $mapping->getFieldTableName($type->getKey('published'));
    $baseTable = $mapping->getBaseTable();

    // First delete all unpublished records from the base data table.
    $count = $this->database->delete($dataTable)
      ->condition($type->getKey('published'), 0)
      ->execute();
    $this->logger->info("Deleting $count unpublished {$type->id()} entities.");

    // Select the set of content that's left over. This is the "OK" set, which
    // we'll use as a whitelist when clearing data out of other tables.
    $okIds = $this->database->select($dataTable, 'b')
      ->fields('b', [$type->getKey('id')]);

    // Clean the base table, if necessary.
    if ($dataTable !== $baseTable) {
      $this->database->delete($mapping->getBaseTable())
        ->condition($type->getKey('id'), $okIds, 'NOT IN')
        ->execute();
    }
    if ($type->isRevisionable()) {
      // Clean the revision table.
      $this->database->delete($mapping->getRevisionTable())
        ->condition($type->getKey('id'), $okIds, 'NOT IN')
        ->execute();

      // Clean the revision data table, if there is one.
      if ($revisionDataTable = $mapping->getRevisionDataTable()) {
        $this->database->delete($revisionDataTable)
          ->condition($type->getKey('id'), $okIds, 'NOT IN')
          ->execute();
      }
    }

    // Delete all records from field data tables that don't have a matching
    // record in the base data table.
    /** @var \Drupal\field\FieldConfigInterface $v */
    foreach ($this->getSanitizableFields() as $fieldStorage) {
      $this->logger->info("Deleting unpublished field data for {$type->id()}:{$fieldStorage->getName()}");

      // Clean the field data table.
      $this->database->delete($mapping->getDedicatedDataTableName($fieldStorage))
        ->condition('entity_id', $okIds, 'NOT IN')
        ->execute();

      if ($type->isRevisionable()) {
        // Clean the field revision table.
        $this->database->delete($mapping->getDedicatedRevisionTableName($fieldStorage))
          ->condition('entity_id', $okIds, 'NOT IN')
          ->execute();
      }
    }
  }

  /**
   * Delete all old revisions of current content.
   */
  public function deleteRevisions() {
    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $mapping */
    $mapping = $this->storage->getTableMapping();
    $type = $this->storage->getEntityType();

    if (!$type->isRevisionable()) {
      return;
    }

    // Begin by selecting the revision ids of all of the "active" revisions.
    // This will be used as a whitelist for comparison with the revision tables.
    $okIds = $this->database->select($mapping->getBaseTable(), 'b')
      ->fields('b', [$type->getKey('revision')]);

    // Delete all records from the main revision table that aren't in the
    // active set.
    $count = $this->database->delete($mapping->getRevisionTable())
      ->condition($type->getKey('revision'), $okIds, 'NOT IN')
      ->execute();
    $this->logger->info("Deleting {$count} revisions for {$type->id()}");

    // Delete all records from the revision data table that aren't in the
    // active set.
    if ($revisionDataTable = $mapping->getRevisionDataTable()) {
      $this->database->delete($mapping->getRevisionDataTable())
        ->condition($type->getKey('revision'), $okIds, 'NOT IN')
        ->execute();
    }

    // Finally, delete all records from every field table that aren't in the
    // active set.
    foreach ($this->getSanitizableFields() as $fieldStorage) {
      $this->logger->info("Deleting revision field data for {$type->id()}:{$fieldStorage->getName()}");
      $this->database->delete($mapping->getDedicatedRevisionTableName($fieldStorage))
        ->condition('revision_id', $okIds, 'NOT IN')
        ->execute();
    }

    $this->database->delete('key_value')
      ->condition('collection', "pathauto_state.{$type->id()}")
      ->condition('name', $okIds, 'NOT IN');
  }

  /**
   * Remove all revision log messages, which may contain sensitive data.
   */
  private function sanitizeRevisionLogs() {
    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $mapping */
    $mapping = $this->storage->getTableMapping();
    $type = $this->storage->getEntityType();

    if (!$type->isRevisionable() || !$type instanceof ContentEntityType || !$type->getRevisionMetadataKey('revision_log_message')) {
      return;
    }
    $count = $this->database->update($mapping->getRevisionTable())
      ->fields([$type->getRevisionMetadataKey('revision_log_message') => ''])
      ->isNotNull($type->getRevisionMetadataKey('revision_log_message'))
      ->condition($type->getRevisionMetadataKey('revision_log_message'), '', '!=')
      ->execute();

    $this->logger->info("Sanitized {$count} {$type->id()} revision logs.");
  }

  /**
   * Retrieve a list of all fields that have a dedicated table.
   */
  protected function getSanitizableFields() {
    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $mapping */
    $mapping = $this->storage->getTableMapping();
    return array_filter($this->fieldStorageDefinitions, function (FieldStorageDefinitionInterface $definition) use ($mapping) {
      return $mapping->requiresDedicatedTableStorage($definition);
    });
  }

}
