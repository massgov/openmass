<?php

namespace MassGov\Sanitation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;

/**
 * Performs sanitization operations for a single entity type.
 *
 * This class requires that the entity type use SQL storage.
 */
class ContentModerationSanitizer extends SqlEntitySanitizer {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Sets the entity type manager instance.
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Executes sanitizations.
   */
  public function sanitize() {
    $this->deleteDanglingReferences();
    parent::sanitize();
  }

  /**
   * Removes content moderation states where the target entity has been removed.
   *
   * This situation is created by running sanitizations on other entity types.
   * Normally, these records would be cleaned up by Drupal APIs, but since we
   * are dealing directly with the database, the cleanup doesn't happen
   * automatically.
   */
  public function deleteDanglingReferences() {
    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $mapping */
    $mapping = $this->storage->getTableMapping();
    $type = $this->storage->getEntityType();

    foreach ($this->entityTypeManager->getDefinitions() as $target_type_name => $definition) {
      $targetStorage = $this->entityTypeManager->getStorage($target_type_name);
      $targetType = $targetStorage->getEntityType();
      // Determine whether we should be attempting to sanitize moderation states
      // for this entity type.
      if (!$targetType->entityClassImplements(ContentEntityInterface::class)) {
        continue;
      }
      if (!$targetStorage instanceof SqlEntityStorageInterface) {
        continue;
      }
      if (!$targetType->isRevisionable()) {
        continue;
      }

      $this->logger->info("Pruning moderation states for {$targetType->id()}");

      /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $targetMapping */
      $targetMapping = $targetStorage->getTableMapping();

      // Grab a whitelist of currently valid revision IDs for the target entity
      // type.
      $targetOkQuery = $this->database->select($targetMapping->getRevisionTable(), 'b')
        ->fields('b', [$targetType->getKey('revision')]);

      // Delete any references to this entity type that don't match up with what
      // is in the target entity type's revision table.
      $this->database->delete($mapping->getDataTable())
        ->condition('content_entity_type_id', $targetType->id())
        ->condition('content_entity_revision_id', $targetOkQuery, 'NOT IN')
        ->execute();

      $okQuery = $this->database->select($mapping->getDataTable(), 'd')
        ->fields('d', [$type->getKey('revision')]);
      $this->database->delete($mapping->getBaseTable())
        ->condition($type->getKey('revision'), $okQuery, 'NOT IN')
        ->execute();
      $this->database->delete($mapping->getRevisionTable())
        ->condition($type->getKey('revision'), $okQuery, 'NOT IN')
        ->execute();
      $this->database->delete($mapping->getRevisionDataTable())
        ->condition($type->getKey('revision'), $okQuery, 'NOT IN')
        ->execute();
    }
  }

}
