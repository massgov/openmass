<?php

namespace MassGov\Sanitation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;

/**
 * Performs sanitization operations for media entities.
 *
 * This class requires that the entity type use SQL storage.
 */
class MediaSanitizer extends SqlEntitySanitizer {

  /**
   * Executes sanitizations.
   */
  public function sanitize() {
    $this->deleteInternalData();
    parent::sanitize();
  }

  /**
   * Removes the internal notes field data.
   */
  public function deleteInternalData() {
    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $mapping */
    $mapping = $this->storage->getTableMapping();

    foreach($this->getSanitizableFields() as $fieldStorage) {
      if($fieldStorage->getName() === 'field_internal_notes') {
        $this->logger->info("Deleting data for the media entity's internal notes field.");
        $this->database->truncate($mapping->getDedicatedDataTableName($fieldStorage))->execute();
        $this->database->truncate($mapping->getDedicatedRevisionTableName($fieldStorage))->execute();
      }
    }
  }

}
