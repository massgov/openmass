<?php

namespace Drupal\Tests\trashbin\ExistingSite;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\trashbin\Drush\Commands\TrashbinCommands;
use Drupal\trashbin\TrashbinPurgeCandidateQuery;
use Drush\Config\DrushConfig;
use Drush\Log\DrushLoggerManager;
use MassGov\Dtt\MassExistingSiteBase;
use Psr\Log\NullLogger;
use Robo\Config\Config as RoboConfig;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Existing-site coverage for the trashbin:purge command flow.
 *
 * These tests invoke the real purge against the shared live DB, so their
 * isolation is defended twice: setUp() sweeps epoch-stamped leftovers from
 * interrupted prior runs, and every destructive call is preceded by a loud
 * precondition assertion that the fixtures — and only the fixtures — occupy
 * the max-N candidate window.
 *
 * @coversDefaultClass \Drupal\trashbin\Drush\Commands\TrashbinCommands
 *
 * @group trashbin
 */
class TrashbinCommandsTest extends MassExistingSiteBase {

  use MediaCreationTrait;
  use TrashbinFixturesTrait;

  /**
   * The cutoff the command derives from --days-ago=180.
   */
  private int $cutoff180;

  protected function setUp(): void {
    parent::setUp();
    $this->cutoff180 = time() - 180 * 86400;
    $this->sweepEpochFixtures();
  }

  /**
   * Purge deletes the oldest trashed fixtures and spares recent trash.
   */
  public function testPurgeDeletesOldestTrashedFixtures() {
    $storage = \Drupal::entityTypeManager()->getStorage('node');

    $nids = [
      $this->createTrashedOrgPageWithActivity(10000),
      $this->createTrashedOrgPageWithActivity(20000),
      $this->createTrashedOrgPageWithActivity(30000),
    ];
    // Control: trashed just now, far newer than the 180-day cutoff.
    $controlNid = $this->createTrashedOrgPage();

    $this->assertWindowContains('node', 3, $nids);

    $command = $this->createCommand();
    $command->purge('node', ['max' => 3, 'days-ago' => 180]);

    $storage->resetCache(array_merge($nids, [$controlNid]));
    foreach ($nids as $nid) {
      $this->assertNull($storage->load($nid), "Fixture $nid must be deleted.");
    }
    $this->assertNotNull($storage->load($controlNid), 'Recently trashed control node must survive.');
  }

  /**
   * Simulate mode must not delete anything.
   */
  public function testSimulateDoesNotDelete() {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $nid = $this->createTrashedOrgPageWithActivity(10000);

    $this->assertWindowContains('node', 1, [$nid]);

    $command = $this->createCommand(simulate: TRUE);
    $command->purge('node', ['max' => 1, 'days-ago' => 180]);

    $storage->resetCache([$nid]);
    $this->assertNotNull($storage->load($nid), 'Simulate must leave the fixture intact.');
  }

  /**
   * A candidate whose entity API state is not trash is never deleted.
   *
   * Even when the SQL layer considers it eligible, the loaded entity is the
   * authority on the current moderation state.
   */
  public function testEntityApiTrashCheckBlocksDeletion() {
    $storage = \Drupal::entityTypeManager()->getStorage('node');

    $node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Trashbin command draft control ' . uniqid('', TRUE),
      'field_sub_title' => $this->randomString(20),
      'moderation_state' => 'draft',
    ]);
    $nid = (int) $node->id();
    $vid = (int) $node->getRevisionId();
    $this->forceNodeActivity($nid, $vid, 10000);

    // Newest moderation record says "trash" while the entity API reports
    // "draft": the SQL candidate layer is fooled, the command must not be.
    $syntheticId = $this->insertModerationRow([
      'workflow' => 'topic_page',
      'moderation_state' => 'trash',
      'content_entity_type_id' => 'node',
      'content_entity_id' => $nid,
      'content_entity_revision_id' => $vid,
    ]);

    try {
      $service = $this->purgeCandidateQuery();
      $this->assertTrue($service->isEntityEligible('node', $nid, 50000), 'Sanity: the SQL layer must consider the node eligible.');
      $this->assertWindowContains('node', 1, [$nid]);

      $command = $this->createCommand();
      $command->purge('node', ['max' => 1, 'days-ago' => 180]);

      $storage->resetCache([$nid]);
      $this->assertNotNull($storage->load($nid), 'A draft node must never be deleted regardless of stale SQL state.');
    }
    finally {
      $this->deleteModerationRow($syntheticId);
    }
  }

  /**
   * One entity failing must not abort the batch, and the run exits non-zero.
   *
   * Three candidates: one whose load throws, one whose load returns NULL
   * (deleted concurrently), and a healthy one — the batch must survive the
   * first two and still delete the third.
   */
  public function testFailureOnOneEntityDoesNotAbortBatch() {
    $realStorage = \Drupal::entityTypeManager()->getStorage('node');

    $poisonNid = $this->createTrashedOrgPageWithActivity(9000);
    $ghostNid = $this->createTrashedOrgPageWithActivity(9250);
    $healthyNid = $this->createTrashedOrgPageWithActivity(9500);
    $this->assertWindowContains('node', 3, [$poisonNid, $ghostNid, $healthyNid]);

    $storageMock = $this->createMock(EntityStorageInterface::class);
    $storageMock->method('load')->willReturnCallback(function ($id) use ($poisonNid, $ghostNid, $realStorage) {
      if ((int) $id === $poisonNid) {
        throw new \RuntimeException('Simulated corrupted entity.');
      }
      if ((int) $id === $ghostNid) {
        return NULL;
      }
      return $realStorage->load($id);
    });
    $storageMock->method('resetCache');

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->method('getStorage')->with('node')->willReturn($storageMock);

    $command = $this->createCommand(etm: $etm);

    try {
      $command->purge('node', ['max' => 3, 'days-ago' => 180]);
      $this->fail('A failed entity must make the run exit non-zero via RuntimeException.');
    }
    catch (\RuntimeException $e) {
      $this->assertStringContainsString('Failed to purge 1', $e->getMessage());
    }

    $realStorage->resetCache([$poisonNid, $ghostNid, $healthyNid]);
    $this->assertNotNull($realStorage->load($poisonNid), 'The poison entity itself must remain untouched.');
    $this->assertNotNull($realStorage->load($ghostNid), 'A NULL load must be skipped, not treated as a failure.');
    $this->assertNull($realStorage->load($healthyNid), 'The batch must continue past failures and delete the healthy entity.');
  }

  /**
   * With more eligible candidates than --max, the oldest ones are deleted.
   */
  public function testMaxCapsTheBatchOldestFirst() {
    $storage = \Drupal::entityTypeManager()->getStorage('node');

    $nidOldest = $this->createTrashedOrgPageWithActivity(10000);
    $nidMiddle = $this->createTrashedOrgPageWithActivity(20000);
    $nidNewest = $this->createTrashedOrgPageWithActivity(30000);
    $this->assertWindowContains('node', 2, [$nidOldest, $nidMiddle]);

    $command = $this->createCommand();
    $command->purge('node', ['max' => 2, 'days-ago' => 180]);

    $storage->resetCache([$nidOldest, $nidMiddle, $nidNewest]);
    $this->assertNull($storage->load($nidOldest), 'Oldest fixture must be deleted within the cap.');
    $this->assertNull($storage->load($nidMiddle), 'Second-oldest fixture must be deleted within the cap.');
    $this->assertNotNull($storage->load($nidNewest), 'The fixture beyond --max must survive.');
  }

  /**
   * The command purges media through the media-specific revision column.
   */
  public function testMediaCommandPurge() {
    $storage = \Drupal::entityTypeManager()->getStorage('media');
    $mid = $this->createTrashedDocumentMediaWithActivity(10000);

    $this->assertWindowContains('media', 1, [$mid]);

    $command = $this->createCommand();
    $command->purge('media', ['max' => 1, 'days-ago' => 180]);

    $storage->resetCache([$mid]);
    $this->assertNull($storage->load($mid), 'The media fixture must be deleted by the command.');
  }

  /**
   * The documented --days-ago=0 delete-all mode and the cap boundary hold.
   */
  public function testDaysAgoZeroIsAccepted() {
    $nid = $this->createTrashedOrgPage();

    $command = $this->createCommand();
    $command->purge('node', ['max' => 0, 'days-ago' => 0]);
    $command->purge('node', ['max' => 0, 'days-ago' => TrashbinCommands::DAYS_AGO_MAX]);

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$nid]);
    $this->assertNotNull($storage->load($nid), 'max=0 must delete nothing while days-ago=0 stays valid.');
  }

  /**
   * Malformed options are rejected instead of coercing to delete-all.
   */
  public function testMalformedOptionsAreRejected() {
    $command = $this->createCommand();

    foreach (['abc', '-5', '', '18O', TRUE, '426000000000000', (string) (TrashbinCommands::DAYS_AGO_MAX + 1)] as $bad) {
      try {
        $command->purge('node', ['max' => 1, 'days-ago' => $bad]);
        $this->fail(sprintf('days-ago %s must be rejected.', var_export($bad, TRUE)));
      }
      catch (\InvalidArgumentException $e) {
        $this->assertStringContainsString('--days-ago', $e->getMessage());
      }
    }

    foreach (['abc', TRUE] as $bad) {
      try {
        $command->purge('node', ['max' => $bad, 'days-ago' => 180]);
        $this->fail(sprintf('max %s must be rejected.', var_export($bad, TRUE)));
      }
      catch (\InvalidArgumentException $e) {
        $this->assertStringContainsString('--max', $e->getMessage());
      }
    }
  }

  /**
   * Asserts that the max-N candidate window holds exactly the given IDs.
   */
  private function assertWindowContains(string $entity_type, int $max, array $expected): void {
    $actual = array_map('intval', $this->purgeCandidateQuery()->getCandidateIds($entity_type, $max, $this->cutoff180));
    $this->assertSame($expected, $actual, 'Precondition failed: fixtures do not occupy the candidate window — refusing to run the destructive command against unknown rows.');
  }

  /**
   * Deletes epoch-stamped leftovers from interrupted prior runs.
   *
   * Without this sweep a leftover fixture would fill the max-N window and
   * silently turn the deletion-guard tests vacuous.
   */
  private function sweepEpochFixtures(): void {
    $map = [
      'node' => ['node_field_data', 'nid', 'title'],
      'media' => ['media_field_data', 'mid', 'name'],
    ];
    foreach ($map as $entity_type => [$table, $key, $label]) {
      $ids = \Drupal::database()->query('SELECT DISTINCT ' . $key . ' FROM {' . $table . '} WHERE changed < 100000 AND ' . $label . " LIKE 'Trashbin %'")->fetchCol();
      if ($ids) {
        $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
        $storage->delete($storage->loadMultiple($ids));
      }
    }
  }

  /**
   * Builds the command the same way Drush would, with test config.
   */
  private function createCommand(bool $simulate = FALSE, ?EntityTypeManagerInterface $etm = NULL): TrashbinCommands {
    $command = new TrashbinCommands(
      $etm ?? \Drupal::entityTypeManager(),
      \Drupal::time(),
      $this->purgeCandidateQuery()
    );
    $logger = new DrushLoggerManager();
    $logger->add('test', new NullLogger());
    $command->setLogger($logger);
    $config = new DrushConfig();
    $config->set(RoboConfig::SIMULATE, $simulate);
    $command->setConfig($config);
    return $command;
  }

  private function purgeCandidateQuery(): TrashbinPurgeCandidateQuery {
    return \Drupal::service(TrashbinPurgeCandidateQuery::class);
  }

}
