<?php

namespace Drupal\Tests\trashbin\ExistingSite;

use Drupal\Core\Database\Connection;
use Drupal\file\Entity\File;
use Drupal\trashbin\TrashbinPurgeCandidateQuery;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Existing-site coverage for trash purge candidate selection.
 *
 * @coversDefaultClass \Drupal\trashbin\TrashbinPurgeCandidateQuery
 *
 * @group trashbin
 */
class TrashbinPurgeCandidateQueryTest extends MassExistingSiteBase {

  use MediaCreationTrait;

  /**
   * List-fetch limit for fixture assertions.
   *
   * Isolation from the shared site DB comes from the cutoff, not from this
   * limit: fixtures get forced epoch-era last-activity timestamps while all
   * real rows have modern timestamps, so a low cutoff excludes the site's
   * trash entirely. The limit only needs to absorb rare leftovers from
   * interrupted prior runs.
   */
  private const PURGE_TEST_FETCH_MAX = 1000;

  /**
   * Oldest GREATEST(changed, revision_timestamp) rows must sort first.
   */
  public function testCandidateIdsAreOrderedOldestActivityFirst() {
    $cutoff = 50000;
    $nidNewest = $this->createTrashedOrgPageWithActivity(30000);
    $nidMiddle = $this->createTrashedOrgPageWithActivity(20000);
    $nidOldest = $this->createTrashedOrgPageWithActivity(10000);

    $query = $this->createPurgeCandidateQuery();
    $ids = array_map('intval', $query->getCandidateIds('node', self::PURGE_TEST_FETCH_MAX, $cutoff));

    $posOldest = array_search($nidOldest, $ids, TRUE);
    $posMiddle = array_search($nidMiddle, $ids, TRUE);
    $posNewest = array_search($nidNewest, $ids, TRUE);

    $this->assertNotFalse($posOldest, 'Oldest fixture should appear in purge candidate list.');
    $this->assertNotFalse($posMiddle, 'Middle fixture should appear in purge candidate list.');
    $this->assertNotFalse($posNewest, 'Newest fixture should appear in purge candidate list.');
    $this->assertLessThan($posMiddle, $posOldest, 'Oldest activity must sort before middle.');
    $this->assertLessThan($posNewest, $posMiddle, 'Middle activity must sort before newest.');
  }

  /**
   * Rows at or after the cutoff are excluded.
   */
  public function testCutoffExcludesEntitiesWhoseLastActivityIsNotOldEnough() {
    $cutoff = 50000;
    $nidEligible = $this->createTrashedOrgPageWithActivity(10000);
    $nidTooRecent = $this->createTrashedOrgPageWithActivity(60000);

    $query = $this->createPurgeCandidateQuery();
    $ids = array_map('intval', $query->getCandidateIds('node', self::PURGE_TEST_FETCH_MAX, $cutoff));

    $this->assertContains($nidEligible, $ids);
    $this->assertNotContains($nidTooRecent, $ids);
  }

  /**
   * Published nodes must never appear in trash purge candidates.
   *
   * The published fixture gets epoch-era timestamps, so with a broken
   * moderation filter it would be the globally oldest row and would appear;
   * isEntityEligible() additionally checks it without any LIMIT window.
   */
  public function testPublishedNodesAreNeverCandidates() {
    $node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Trashbin purge published control ' . uniqid('', TRUE),
      'field_sub_title' => $this->randomString(20),
      'moderation_state' => 'published',
    ]);
    $nid = (int) $node->id();
    $this->forceNodeActivity($nid, (int) $node->getRevisionId(), 5000);

    $query = $this->createPurgeCandidateQuery();

    $ids = array_map('intval', $query->getCandidateIds('node', self::PURGE_TEST_FETCH_MAX, 50000));
    $this->assertNotContains($nid, $ids);

    $this->assertFalse($query->isEntityEligible('node', $nid, time() + 60));
  }

  /**
   * Cross-type revision ID collisions must not make a node purgeable.
   *
   * Regression test: the original purge implementation joined moderation
   * state by revision ID alone, so a trashed moderation row of another
   * entity type whose revision ID collided with a node's vid deleted
   * published nodes.
   */
  public function testTrashedRowOfOtherEntityTypeDoesNotMakeNodeEligible() {
    $node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Trashbin purge cross-type control ' . uniqid('', TRUE),
      'field_sub_title' => $this->randomString(20),
      'moderation_state' => 'published',
    ]);
    $nid = (int) $node->id();
    $vid = (int) $node->getRevisionId();
    $this->forceNodeActivity($nid, $vid, 5000);

    $syntheticId = $this->insertModerationRow([
      'workflow' => 'media_states',
      'moderation_state' => 'trash',
      'content_entity_type_id' => 'media',
      'content_entity_id' => 999999999,
      'content_entity_revision_id' => $vid,
    ]);

    try {
      $query = $this->createPurgeCandidateQuery();
      $ids = array_map('intval', $query->getCandidateIds('node', self::PURGE_TEST_FETCH_MAX, 50000));
      $this->assertNotContains($nid, $ids);
      $this->assertFalse($query->isEntityEligible('node', $nid, time() + 60));
    }
    finally {
      $this->deleteModerationRow($syntheticId);
    }
  }

  /**
   * A trash row shadowed by a newer moderation record is not purgeable.
   *
   * Workflow migrations left such pairs in the DB: an old editorial/trash
   * row plus the current bundle-workflow row, both pointing at the same
   * node, where the entity API reports the newer state.
   */
  public function testStaleTrashRowShadowedByNewerRecordIsNotEligible() {
    $nid = $this->createTrashedOrgPageWithActivity(10000);

    $query = $this->createPurgeCandidateQuery();
    $this->assertTrue($query->isEntityEligible('node', $nid, 50000), 'Sanity: trashed fixture is eligible before shadowing.');

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    $syntheticId = $this->insertModerationRow([
      'workflow' => 'topic_page',
      'moderation_state' => 'draft',
      'content_entity_type_id' => 'node',
      'content_entity_id' => $nid,
      'content_entity_revision_id' => (int) $node->getRevisionId(),
    ]);

    try {
      $this->assertFalse($query->isEntityEligible('node', $nid, 50000), 'A newer non-trash moderation record must shadow the stale trash row.');
      $ids = array_map('intval', $query->getCandidateIds('node', self::PURGE_TEST_FETCH_MAX, 50000));
      $this->assertNotContains($nid, $ids);
    }
    finally {
      $this->deleteModerationRow($syntheticId);
    }
  }

  /**
   * Media candidates work through the media revision_created column.
   */
  public function testMediaCandidatesUseRevisionCreatedColumn() {
    $destination = 'public://' . $this->randomMachineName(12) . '.txt';
    \Drupal::service('file_system')->copy('core/tests/Drupal/Tests/Component/FileCache/Fixtures/llama-23.txt', $destination, TRUE);
    $file = File::create(['uri' => $destination]);
    $file->setPermanent();
    $file->save();
    $this->markEntityForCleanup($file);

    $media = $this->createMedia([
      'bundle' => 'document',
      'name' => 'Trashbin purge media candidate ' . uniqid('', TRUE),
      'field_title' => 'Trashbin purge media candidate',
      'field_upload_file' => ['target_id' => $file->id()],
      'moderation_state' => 'published',
    ]);
    $media->set('moderation_state', 'trash');
    $media->save();

    $mid = (int) $media->id();
    $vid = (int) $media->getRevisionId();
    $this->getConnection()->update('media_field_data')
      ->fields(['changed' => 10000])
      ->condition('mid', $mid)
      ->execute();
    $this->getConnection()->update('media_revision')
      ->fields(['revision_created' => 10000])
      ->condition('vid', $vid)
      ->execute();

    $query = $this->createPurgeCandidateQuery();
    $ids = array_map('intval', $query->getCandidateIds('media', self::PURGE_TEST_FETCH_MAX, 50000));
    $this->assertContains($mid, $ids);
    $this->assertTrue($query->isEntityEligible('media', $mid, 50000));
  }

  /**
   * A limit of zero returns no candidates.
   */
  public function testMaxZeroReturnsNoCandidates() {
    $this->createTrashedOrgPageWithActivity(10000);

    $query = $this->createPurgeCandidateQuery();
    $ids = $query->getCandidateIds('node', 0, 50000);

    $this->assertSame([], $ids);
  }

  /**
   * Restored (non-trash) entities fail the pre-delete eligibility check.
   */
  public function testRestoredEntityIsNotEligible() {
    $cutoff = 50000;
    $nid = $this->createTrashedOrgPageWithActivity(10000);

    $query = $this->createPurgeCandidateQuery();
    $this->assertTrue($query->isEntityEligible('node', $nid, $cutoff));

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    $node->set('moderation_state', 'published');
    $node->save();

    $this->assertFalse($query->isEntityEligible('node', $nid, $cutoff));
  }

  /**
   * Structurally unsupported entity types are rejected loudly.
   */
  public function testUnsupportedEntityTypeIsRejected() {
    $query = $this->createPurgeCandidateQuery();
    $this->expectException(\InvalidArgumentException::class);
    $query->getCandidateIds('user', 10, 50000);
  }

  /**
   * Builds the query helper the same way the container would.
   */
  private function createPurgeCandidateQuery(): TrashbinPurgeCandidateQuery {
    return new TrashbinPurgeCandidateQuery(
      $this->getConnection(),
      \Drupal::entityTypeManager()
    );
  }

  private function getConnection(): Connection {
    return \Drupal::database();
  }

  /**
   * Creates an org_page in trash and forces last-activity timestamps in SQL.
   */
  private function createTrashedOrgPageWithActivity(int $activityTimestamp): int {
    $node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Trashbin purge candidate ' . uniqid('', TRUE),
      'field_sub_title' => $this->randomString(20),
      'moderation_state' => 'published',
    ]);
    $node->set('moderation_state', 'trash');
    $node->save();

    $nid = (int) $node->id();
    $this->forceNodeActivity($nid, (int) $node->getRevisionId(), $activityTimestamp);

    return $nid;
  }

  /**
   * Forces changed + revision_timestamp for a node directly in SQL.
   */
  private function forceNodeActivity(int $nid, int $vid, int $activityTimestamp): void {
    $this->getConnection()->update('node_field_data')
      ->fields(['changed' => $activityTimestamp])
      ->condition('nid', $nid)
      ->execute();
    $this->getConnection()->update('node_revision')
      ->fields(['revision_timestamp' => $activityTimestamp])
      ->condition('vid', $vid)
      ->execute();
  }

  /**
   * Inserts a synthetic moderation state row; returns its id for cleanup.
   */
  private function insertModerationRow(array $values): int {
    $id = (int) $this->getConnection()->query('SELECT MAX(id) FROM {content_moderation_state_field_data}')->fetchField() + 1000;
    $this->getConnection()->insert('content_moderation_state_field_data')
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
    $this->getConnection()->delete('content_moderation_state_field_data')
      ->condition('id', $id)
      ->execute();
  }

}
