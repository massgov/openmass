<?php

namespace Drupal\Tests\trashbin\ExistingSite;

use Drupal\Core\Database\Connection;
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
  use TrashbinFixturesTrait;

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
   * The full live candidate list contains no duplicate IDs.
   *
   * A live-data canary: it can only fail once real translated trash exists,
   * so the langcode correlation itself is pinned by
   * testTranslatedEntityMatchesOnceAndOnlyViaDefaultTranslation().
   */
  public function testCandidateIdsContainNoDuplicates() {
    $query = $this->createPurgeCandidateQuery();
    $ids = $query->getCandidateIds('node', 500000, time() + 60);
    $this->assertSame(count($ids), count(array_unique($ids)), 'Candidate list must not contain duplicate IDs.');
  }

  /**
   * Rows at or after the cutoff are excluded; strictly-older rows are not.
   */
  public function testCutoffExcludesEntitiesWhoseLastActivityIsNotOldEnough() {
    $cutoff = 50000;
    $nidEligible = $this->createTrashedOrgPageWithActivity(10000);
    $nidBoundary = $this->createTrashedOrgPageWithActivity(50000);
    $nidTooRecent = $this->createTrashedOrgPageWithActivity(60000);

    $query = $this->createPurgeCandidateQuery();
    $ids = array_map('intval', $query->getCandidateIds('node', self::PURGE_TEST_FETCH_MAX, $cutoff));

    $this->assertContains($nidEligible, $ids);
    $this->assertNotContains($nidBoundary, $ids, 'Activity exactly at the cutoff must be excluded (strict comparison).');
    $this->assertNotContains($nidTooRecent, $ids);
  }

  /**
   * A NULL revision timestamp falls back to changed instead of excluding.
   */
  public function testNullRevisionTimestampFallsBackToChanged() {
    $nid = $this->createTrashedOrgPageWithActivity(10000);
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    $this->getConnection()->update('node_revision')
      ->fields(['revision_timestamp' => NULL])
      ->condition('vid', (int) $node->getRevisionId())
      ->execute();

    $query = $this->createPurgeCandidateQuery();
    $ids = array_map('intval', $query->getCandidateIds('node', self::PURGE_TEST_FETCH_MAX, 50000));
    $this->assertContains($nid, $ids, 'NULL revision timestamp must not silently exclude the row forever.');
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
   * published nodes. Two synthetic rows are used: one excluded by the
   * entity-id condition, and one matching entity id AND revision id that
   * only the content_entity_type_id condition can exclude.
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

    $syntheticIds = [];
    $syntheticIds[] = $this->insertModerationRow([
      'workflow' => 'media_states',
      'moderation_state' => 'trash',
      'content_entity_type_id' => 'media',
      'content_entity_id' => 999999999,
      'content_entity_revision_id' => $vid,
    ]);
    // Same entity id and revision id as the node: only the entity-type
    // condition in the join excludes this one.
    $syntheticIds[] = $this->insertModerationRow([
      'workflow' => 'media_states',
      'moderation_state' => 'trash',
      'content_entity_type_id' => 'media',
      'content_entity_id' => $nid,
      'content_entity_revision_id' => $vid,
    ]);

    try {
      $query = $this->createPurgeCandidateQuery();
      $ids = array_map('intval', $query->getCandidateIds('node', self::PURGE_TEST_FETCH_MAX, 50000));
      $this->assertNotContains($nid, $ids);
      $this->assertFalse($query->isEntityEligible('node', $nid, time() + 60));
    }
    finally {
      foreach ($syntheticIds as $syntheticId) {
        $this->deleteModerationRow($syntheticId);
      }
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
    $shadowedBefore = $query->countShadowedTrashRows('node');

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
      $this->assertSame($shadowedBefore + 1, $query->countShadowedTrashRows('node'), 'The shadowed-rows diagnostic must count exactly the shadowed fixture on top of pre-existing rows.');
    }
    finally {
      $this->deleteModerationRow($syntheticId);
    }
  }

  /**
   * Media candidates work through the media revision_created column.
   */
  public function testMediaCandidatesUseRevisionCreatedColumn() {
    $mid = $this->createTrashedDocumentMediaWithActivity(10000);

    $query = $this->createPurgeCandidateQuery();
    $ids = array_map('intval', $query->getCandidateIds('media', self::PURGE_TEST_FETCH_MAX, 50000));
    $this->assertContains($mid, $ids);
    $this->assertTrue($query->isEntityEligible('media', $mid, 50000));
  }

  /**
   * A just-trashed entity is eligible against a start-time cutoff.
   *
   * This is the query-level half of the --days-ago=0 contract: everything
   * trashed before the command started qualifies regardless of age.
   */
  public function testJustTrashedEntityIsEligibleWithStartTimeCutoff() {
    $nid = $this->createTrashedOrgPage();

    $query = $this->createPurgeCandidateQuery();
    $this->assertTrue($query->isEntityEligible('node', $nid, time() + 60));
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
    foreach (['user', 'paragraph'] as $entity_type) {
      try {
        $query->getCandidateIds($entity_type, 10, 50000);
        $this->fail(sprintf('Entity type "%s" must be rejected.', $entity_type));
      }
      catch (\InvalidArgumentException $e) {
        $this->assertStringContainsString($entity_type, $e->getMessage());
      }
    }
  }

  /**
   * A translated entity matches once, and only via its default translation.
   *
   * No moderated bundle on this site is translatable, so the multi-langcode
   * shape is reproduced with raw rows mirroring the real schema: an extra
   * "es" row in node_field_data (default_langcode=0) plus matching "es"
   * moderation rows. Guards both join conditions: langcode correlation
   * (no cartesian fan-out) and default-translation-only matching (a trashed
   * non-default translation alone never deletes the whole entity).
   */
  public function testTranslatedEntityMatchesOnceAndOnlyViaDefaultTranslation() {
    $db = $this->getConnection();
    $query = $this->createPurgeCandidateQuery();

    // Case A: default translation trashed, "es" translation rows also trash
    // -> exactly one candidate row.
    $nidTrashed = $this->createTrashedOrgPageWithActivity(10000);
    $this->insertEsTranslationRows($nidTrashed, 'trash');

    // Case B: default translation published, only the "es" moderation row is
    // trash -> never a candidate.
    $node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Trashbin purge translated control ' . uniqid('', TRUE),
      'field_sub_title' => $this->randomString(20),
      'moderation_state' => 'published',
    ]);
    $nidPublished = (int) $node->id();
    $this->forceNodeActivity($nidPublished, (int) $node->getRevisionId(), 5000);
    $this->insertEsTranslationRows($nidPublished, 'trash');

    try {
      $ids = array_map('intval', $query->getCandidateIds('node', self::PURGE_TEST_FETCH_MAX, 50000));
      $this->assertSame([$nidTrashed], array_values(array_intersect($ids, [$nidTrashed])), 'Fully trashed translated entity must appear exactly once.');
      $this->assertNotContains($nidPublished, $ids, 'A trashed non-default translation alone must not make the entity a candidate.');
      $this->assertFalse($query->isEntityEligible('node', $nidPublished, time() + 60));
    }
    finally {
      $db->delete('node_field_data')->condition('nid', [$nidTrashed, $nidPublished], 'IN')->condition('langcode', 'es')->execute();
      $db->delete('content_moderation_state_field_data')->condition('content_entity_id', [$nidTrashed, $nidPublished], 'IN')->condition('content_entity_type_id', 'node')->condition('langcode', 'es')->execute();
    }
  }

  /**
   * Copies a node's "en" rows as an "es" translation directly in SQL.
   */
  private function insertEsTranslationRows(int $nid, string $moderation_state): void {
    $db = $this->getConnection();

    $base = (array) $db->query('SELECT * FROM {node_field_data} WHERE nid = :nid AND langcode = :lang', [':nid' => $nid, ':lang' => 'en'])->fetchObject();
    $base['langcode'] = 'es';
    $base['default_langcode'] = 0;
    $db->insert('node_field_data')->fields($base)->execute();

    $cms = (array) $db->query("SELECT * FROM {content_moderation_state_field_data} WHERE content_entity_type_id = 'node' AND content_entity_id = :nid AND langcode = 'en'", [':nid' => $nid])->fetchObject();
    $cms['langcode'] = 'es';
    $cms['default_langcode'] = 0;
    $cms['moderation_state'] = $moderation_state;
    $db->insert('content_moderation_state_field_data')->fields($cms)->execute();
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

}
