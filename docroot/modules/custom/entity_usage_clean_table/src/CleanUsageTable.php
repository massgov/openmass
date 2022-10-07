<?php

namespace Drupal\entity_usage_clean_table;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\RevisionableInterface;

/**
 * Cleans entity usage table from repeated records.
 */
class CleanUsageTable {

  /**
   * Database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * Entity Type Manager Service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Returns a CleanUsageTable object with populated properties.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Database\Connection $database
   *
   * @return void
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
  }

  /**
   * Execute private methods for cleanining.
   */
  public function clean() {
    $this->deleteRecordsWithNonCurrentRevisions();
    $this->deleteRecordsPointingThemselves();
  }

  /**
   * Delete references from other revisions.
   */
  private function deleteOtherRevisions($id, $vid, $source_type, $suffix = '') {
    $this->database->delete('entity_usage')
      ->condition('source_type', $source_type)
      ->condition('source_id' . $suffix, $id)
      ->condition('source_vid' . $suffix, $vid, '<>')
      ->execute();
  }

  /**
   * Deletes records where the target is the same as the source.
   */
  private function deleteRecordsPointingThemselves() {
    $this->database->query("
      DELETE FROM entity_usage
      WHERE target_id = source_id
    ")->execute();

    $this->database->query("
      DELETE FROM entity_usage
      WHERE target_id_string = source_id_string
    ")->execute();

  }

  /**
   * Returns records with repeated entity IDs.
   */
  private function getRepeatedRecords($field) {
    return $this->database->query("
        SELECT
          {$field}, source_type, COUNT({$field})
        FROM entity_usage
        GROUP BY {$field} HAVING COUNT({$field}) > 1
    ")->fetchAll();
  }

  /**
   * Deletes records with revisions that are not the current one.
   */
  private function deleteRecordsWithNonCurrentRevisions() {
    $res = [];
    $res[''] = $this->getRepeatedRecords('source_id');
    $res['_string'] = $this->getRepeatedRecords('source_id_string');

    foreach ($res as $key => $records) {

      foreach ($records as $record) {
        $source_type = $record->source_type;
        $source_id = $record->source_id;
        $storage = $this->entityTypeManager->getStorage($source_type);
        /** @var \Drupal\Core\Entity\RevisionableInterface */
        $entity = $storage->load($source_id);
        if (!($entity instanceof RevisionableInterface)) {
          continue;
        }
        $vid = $entity->getRevisionId();
        $this->deleteOtherRevisions($source_id, $vid, $source_type, $key);
      }
    }
  }

}
