<?php

namespace Drupal\Tests\trashbin\ExistingSite;

use Drupal\Core\Database\Connection;
use Drupal\trashbin\TrashbinPurgeCandidateQuery;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Existing-site coverage for trash purge candidate selection.
 *
 * @coversDefaultClass \Drupal\trashbin\TrashbinPurgeCandidateQuery
 *
 * @group trashbin
 */
class TrashbinPurgeCandidateQueryTest extends MassExistingSiteBase {

  /**
   * Fetch enough rows to include our fixtures; the site DB has other trash.
   *
   * @see self::testCandidateIdsAreOrderedOldestActivityFirst()
   */
  private const PURGE_TEST_FETCH_MAX = 500000;

  /**
   * Oldest GREATEST(changed, revision_timestamp) rows must sort first.
   *
   * Uses a low cutoff and forced last-activity timestamps so typical site
   * trash (recent Unix timestamps) is excluded; we only compete with rare
   * bad rows. We assert relative order among our three fixtures.
   */
  public function testCandidateIdsAreOrderedOldestActivityFirst() {
    $this->drupalLogin($this->createUser([], NULL, TRUE));

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
   *
   * Same low-cutoff pattern as the ordering test so the pool is mostly our
   * fixtures, not the whole site's trash history.
   */
  public function testCutoffExcludesEntitiesWhoseLastActivityIsNotOldEnough() {
    $this->drupalLogin($this->createUser([], NULL, TRUE));

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
   */
  public function testPublishedNodesAreNeverCandidates() {
    $this->drupalLogin($this->createUser([], NULL, TRUE));

    $node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Trashbin purge published control ' . uniqid('', TRUE),
      'field_sub_title' => $this->randomString(20),
      'moderation_state' => 'published',
    ]);
    $nid = (int) $node->id();
    $vid = (int) $node->getRevisionId();
    $ts = time() - 86400 * 400;
    $this->getConnection()->update('node_field_data')
      ->fields(['changed' => $ts])
      ->condition('nid', $nid)
      ->execute();
    $this->getConnection()->update('node_revision')
      ->fields(['revision_timestamp' => $ts])
      ->condition('vid', $vid)
      ->execute();

    $query = $this->createPurgeCandidateQuery();
    $ids = $query->getCandidateIds('node', 50, time() + 60);

    $this->assertNotContains($nid, array_map('intval', $ids));
  }

  /**
   * A limit of zero returns no candidates.
   */
  public function testMaxZeroReturnsNoCandidates() {
    $this->drupalLogin($this->createUser([], NULL, TRUE));
    $this->createTrashedOrgPageWithActivity(time() - 86400 * 400);

    $query = $this->createPurgeCandidateQuery();
    $ids = $query->getCandidateIds('node', 0, time() + 60);

    $this->assertSame([], $ids);
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
    $vid = (int) $node->getRevisionId();

    $this->getConnection()->update('node_field_data')
      ->fields(['changed' => $activityTimestamp])
      ->condition('nid', $nid)
      ->execute();
    $this->getConnection()->update('node_revision')
      ->fields(['revision_timestamp' => $activityTimestamp])
      ->condition('vid', $vid)
      ->execute();

    return $nid;
  }

}
