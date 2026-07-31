<?php

namespace Drupal\Tests\trashbin\ExistingSite;

use Drupal\trashbin\Drush\Commands\TrashbinCommands;
use Drupal\trashbin\TrashbinPurgeCandidateQuery;
use Drush\Config\DrushConfig;
use Drush\Log\DrushLoggerManager;
use MassGov\Dtt\MassExistingSiteBase;
use Psr\Log\NullLogger;
use Robo\Config\Config as RoboConfig;

/**
 * Existing-site coverage for the trashbin:purge command flow.
 *
 * Fixtures get epoch-era last-activity timestamps, which makes them the
 * globally oldest purge candidates (all real rows have modern timestamps),
 * so a run with --max equal to the fixture count deterministically selects
 * only the fixtures and never touches real site trash.
 *
 * @coversDefaultClass \Drupal\trashbin\Drush\Commands\TrashbinCommands
 *
 * @group trashbin
 */
class TrashbinCommandsTest extends MassExistingSiteBase {

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
    $db = \Drupal::database();
    $syntheticId = (int) $db->query('SELECT MAX(id) FROM {content_moderation_state_field_data}')->fetchField() + 1000;
    $db->insert('content_moderation_state_field_data')
      ->fields([
        'id' => $syntheticId,
        'revision_id' => $syntheticId,
        'langcode' => 'en',
        'uid' => 1,
        'workflow' => 'topic_page',
        'moderation_state' => 'trash',
        'content_entity_type_id' => 'node',
        'content_entity_id' => $nid,
        'content_entity_revision_id' => $vid,
        'default_langcode' => 1,
        'revision_translation_affected' => 1,
      ])
      ->execute();

    try {
      $service = $this->purgeCandidateQuery();
      $this->assertTrue($service->isEntityEligible('node', $nid, 50000), 'Sanity: the SQL layer must consider the node eligible.');

      $command = $this->createCommand();
      $command->purge('node', ['max' => 1, 'days-ago' => 180]);

      $storage->resetCache([$nid]);
      $this->assertNotNull($storage->load($nid), 'A draft node must never be deleted regardless of stale SQL state.');
    }
    finally {
      $db->delete('content_moderation_state_field_data')
        ->condition('id', $syntheticId)
        ->execute();
    }
  }

  /**
   * Malformed options are rejected instead of coercing to delete-all.
   */
  public function testMalformedOptionsAreRejected() {
    $command = $this->createCommand();

    foreach (['abc', '-5', '', '18O'] as $bad) {
      try {
        $command->purge('node', ['max' => 1, 'days-ago' => $bad]);
        $this->fail(sprintf('days-ago "%s" must be rejected.', $bad));
      }
      catch (\InvalidArgumentException $e) {
        $this->assertStringContainsString('--days-ago', $e->getMessage());
      }
    }

    try {
      $command->purge('node', ['max' => 'abc', 'days-ago' => 180]);
      $this->fail('Non-numeric max must be rejected.');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertStringContainsString('--max', $e->getMessage());
    }
  }

  /**
   * Builds the command the same way Drush would, with test config.
   */
  private function createCommand(bool $simulate = FALSE): TrashbinCommands {
    $command = new TrashbinCommands(
      \Drupal::entityTypeManager(),
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

  /**
   * Creates an org_page in trash with its natural (recent) timestamps.
   */
  private function createTrashedOrgPage(): int {
    $node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Trashbin command candidate ' . uniqid('', TRUE),
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

}
