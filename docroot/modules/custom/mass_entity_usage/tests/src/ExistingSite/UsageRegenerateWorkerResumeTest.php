<?php

namespace Drupal\Tests\mass_entity_usage\ExistingSite;

use Drupal\mass_entity_usage\EntityUsageQueueBatchManager;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests the batch worker's resume-vs-wipe decision.
 *
 * The worker (queueSourcesBatchWorker) is the only code path deleting usage data
 * (bulkDeleteSources). On resume — saved progress belonging to the current
 * run — it must restore the sandbox and keep existing records; only a fresh
 * start (no progress, or progress from a stale run) may wipe. A regression
 * here silently deletes all usage records while claiming to resume.
 *
 * The tests use the `redirect` entity type so the wipe branch only touches
 * synthetic rows created here (plus a snapshot/restore safety net), never
 * the site's node/paragraph usage data.
 *
 * @group existing-site
 */
class UsageRegenerateWorkerResumeTest extends MassExistingSiteBase {

  private const ENTITY_TYPE = 'redirect';

  /**
   * Saved state values to restore in tearDown, keyed by state key.
   */
  private array $originalState = [];

  /**
   * Snapshot of real usage rows the wipe branch could delete.
   */
  private array $usageRowsSnapshot = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach ($this->managedStateKeys() as $key) {
      $this->originalState[$key] = \Drupal::state()->get($key);
    }
    $this->usageRowsSnapshot = \Drupal::database()
      ->select('entity_usage', 'eu')
      ->fields('eu')
      ->condition('source_type', self::ENTITY_TYPE)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $state = \Drupal::state();
    foreach ($this->originalState as $key => $value) {
      if ($value === NULL) {
        $state->delete($key);
      }
      else {
        $state->set($key, $value);
      }
    }
    $database = \Drupal::database();
    $database->delete('entity_usage')
      ->condition('source_type', self::ENTITY_TYPE)
      ->execute();
    foreach ($this->usageRowsSnapshot as $row) {
      $database->insert('entity_usage')->fields($row)->execute();
    }
    $this->deleteQueueItemsForEntityType(self::ENTITY_TYPE);
    parent::tearDown();
  }

  /**
   * Tests resume restores saved progress and keeps existing usage records.
   */
  public function testResumeKeepsUsageRecordsAndRestoresProgress(): void {
    $runId = 'worker-resume-test.' . time();
    \Drupal::state()->set(EntityUsageQueueBatchManager::ENQUEUE_RUN_ID_KEY, $runId);
    // current_item far above any real redirect ID: the worker finds nothing
    // to enqueue, so the run stays in progress and the queue stays clean.
    \Drupal::state()->set('mass_entity_usage.queue_progress.' . self::ENTITY_TYPE, [
      'run_id' => $runId,
      'progress' => 5,
      'total' => 10,
      'current_item' => 999999999,
      'completed' => FALSE,
    ]);
    $this->insertSentinelUsageRow();

    $context = ['sandbox' => [], 'results' => [], 'finished' => 0, 'message' => ''];
    EntityUsageQueueBatchManager::queueSourcesBatchWorker(self::ENTITY_TYPE, 10, $context);

    $this->assertSame(1, $this->countSentinelUsageRows(), 'Resume must not delete existing usage records.');
    $this->assertSame(5, $context['sandbox']['progress'], 'Resume must restore saved progress.');
    $this->assertSame(10, $context['sandbox']['total']);
    $this->assertSame(999999999, $context['sandbox']['current_item'], 'Resume must continue from the saved position, not from zero.');

    $progress = \Drupal::state()->get('mass_entity_usage.queue_progress.' . self::ENTITY_TYPE);
    $this->assertFalse($progress['completed']);
    $this->assertSame($runId, $progress['run_id']);
  }

  /**
   * Tests progress from a stale run is discarded and records are wiped.
   */
  public function testStaleRunProgressIsDiscardedAndRecordsWiped(): void {
    \Drupal::state()->set(EntityUsageQueueBatchManager::ENQUEUE_RUN_ID_KEY, 'current-run.' . time());
    \Drupal::state()->set('mass_entity_usage.queue_progress.' . self::ENTITY_TYPE, [
      'run_id' => 'stale-run.0',
      'progress' => 5,
      'total' => 10,
      'current_item' => 999999999,
      'completed' => FALSE,
    ]);
    $this->insertSentinelUsageRow();

    $context = ['sandbox' => [], 'results' => [], 'finished' => 0, 'message' => ''];
    EntityUsageQueueBatchManager::queueSourcesBatchWorker(self::ENTITY_TYPE, 1, $context);

    $this->assertSame(0, $this->countSentinelUsageRows(), 'A fresh start must wipe usage records for the entity type.');
    $this->assertNotSame(999999999, $context['sandbox']['current_item'], 'Stale progress must not be resumed.');

    $progress = \Drupal::state()->get('mass_entity_usage.queue_progress.' . self::ENTITY_TYPE);
    $this->assertNotSame('stale-run.0', $progress['run_id'], 'Progress must be re-keyed to the current run.');
  }

  /**
   * State keys this test writes and must restore.
   */
  private function managedStateKeys(): array {
    return [
      EntityUsageQueueBatchManager::ENQUEUE_RUN_ID_KEY,
      'mass_entity_usage.queue_progress.' . self::ENTITY_TYPE,
      'mass_entity_usage.enqueue_completed_at',
    ];
  }

  /**
   * Inserts a sentinel usage row that only bulkDeleteSources() would remove.
   */
  private function insertSentinelUsageRow(): void {
    \Drupal::database()->insert('entity_usage')->fields([
      'target_id' => 999999901,
      'target_type' => 'node',
      'source_id' => 999999902,
      'source_type' => self::ENTITY_TYPE,
      'source_langcode' => 'en',
      'source_vid' => 0,
      'method' => 'test_sentinel',
      'field_name' => 'test_sentinel',
      'count' => 1,
    ])->execute();
  }

  /**
   * Counts sentinel usage rows.
   */
  private function countSentinelUsageRows(): int {
    return (int) \Drupal::database()->select('entity_usage', 'eu')
      ->condition('source_type', self::ENTITY_TYPE)
      ->condition('method', 'test_sentinel')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Removes queue items the fresh-start branch enqueued for an entity type.
   */
  private function deleteQueueItemsForEntityType(string $entityTypeId): void {
    $database = \Drupal::database();
    $items = $database->select('queue', 'q')
      ->fields('q', ['item_id', 'data'])
      ->condition('name', EntityUsageQueueBatchManager::QUEUE_NAME)
      ->execute()
      ->fetchAllKeyed();
    foreach ($items as $itemId => $data) {
      $payload = unserialize($data, ['allowed_classes' => FALSE]);
      if (is_array($payload) && ($payload['entity_type'] ?? '') === $entityTypeId) {
        $database->delete('queue')->condition('item_id', $itemId)->execute();
      }
    }
  }

}
