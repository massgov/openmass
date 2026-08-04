<?php

namespace Drupal\Tests\trashbin\ExistingSite;

use Drupal\file\Entity\File;

/**
 * Shared fixtures for trashbin purge tests.
 *
 * Isolation model: fixtures get epoch-era last-activity timestamps, which
 * makes them the globally oldest purge candidates — every real row in the
 * shared site DB has a modern timestamp. Tests then either use a low cutoff
 * (excluding all real data) or a max-N window (fixtures fill it first).
 */
trait TrashbinFixturesTrait {

  /**
   * Creates an org_page in trash with its natural (recent) timestamps.
   */
  private function createTrashedOrgPage(): int {
    $node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Trashbin purge fixture ' . uniqid('', TRUE),
      'field_sub_title' => $this->randomString(20),
      'moderation_state' => 'published',
    ]);
    $node->set('moderation_state', 'trash');
    $node->save();

    return (int) $node->id();
  }

  /**
   * Creates an org_page in trash and forces last-activity timestamps in SQL.
   */
  private function createTrashedOrgPageWithActivity(int $activityTimestamp): int {
    $nid = $this->createTrashedOrgPage();
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    $this->forceNodeActivity($nid, (int) $node->getRevisionId(), $activityTimestamp);

    return $nid;
  }

  /**
   * Forces changed + revision_timestamp for a node directly in SQL.
   */
  private function forceNodeActivity(int $nid, int $vid, int $activityTimestamp): void {
    $db = \Drupal::database();
    $db->update('node_field_data')
      ->fields(['changed' => $activityTimestamp])
      ->condition('nid', $nid)
      ->execute();
    $db->update('node_revision')
      ->fields(['revision_timestamp' => $activityTimestamp])
      ->condition('vid', $vid)
      ->execute();
  }

  /**
   * Creates a trashed document media and forces last-activity timestamps.
   */
  private function createTrashedDocumentMediaWithActivity(int $activityTimestamp): int {
    $destination = 'public://' . $this->randomMachineName(12) . '.txt';
    \Drupal::service('file_system')->copy('core/tests/Drupal/Tests/Component/FileCache/Fixtures/llama-23.txt', $destination, TRUE);
    $file = File::create(['uri' => $destination]);
    $file->setPermanent();
    $file->save();
    $this->markEntityForCleanup($file);

    $media = $this->createMedia([
      'bundle' => 'document',
      'name' => 'Trashbin purge media fixture ' . uniqid('', TRUE),
      'field_title' => 'Trashbin purge media fixture',
      'field_upload_file' => ['target_id' => $file->id()],
      'moderation_state' => 'published',
    ]);
    $media->set('moderation_state', 'trash');
    $media->save();

    $mid = (int) $media->id();
    $db = \Drupal::database();
    $db->update('media_field_data')
      ->fields(['changed' => $activityTimestamp])
      ->condition('mid', $mid)
      ->execute();
    $db->update('media_revision')
      ->fields(['revision_created' => $activityTimestamp])
      ->condition('vid', (int) $media->getRevisionId())
      ->execute();

    return $mid;
  }

  /**
   * Inserts a synthetic moderation state row; returns its id for cleanup.
   */
  private function insertModerationRow(array $values): int {
    $id = (int) \Drupal::database()->query('SELECT MAX(id) FROM {content_moderation_state_field_data}')->fetchField() + 1000;
    \Drupal::database()->insert('content_moderation_state_field_data')
      ->fields($values + [
        'id' => $id,
        'revision_id' => $id,
        'langcode' => 'en',
        'uid' => 1,
        'default_langcode' => 1,
        'revision_translation_affected' => 1,
      ])
      ->execute();
    return $id;
  }

  private function deleteModerationRow(int $id): void {
    \Drupal::database()->delete('content_moderation_state_field_data')
      ->condition('id', $id)
      ->execute();
  }

}
