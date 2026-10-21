<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_entity_usage\ExistingSite;

use Drupal\mass_entity_usage\EntityUsageQueueBatchManager;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests enqueue progress, resume, and completion guard behaviour.
 *
 * @group existing-site
 * @group mass_entity_usage
 */
class UsageRegenerateEnqueueTest extends MassExistingSiteBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->resetEnqueueState();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->resetEnqueueState();
    parent::tearDown();
  }

  /**
   * Tests that a fresh run clears prior progress and assigns a new run ID.
   */
  public function testBeginFreshEnqueueRunClearsProgressAndSetsRunId(): void {
    $manager = $this->getManager();
    $state = \Drupal::state();

    $state->set('mass_entity_usage.queue_progress.node', [
      'run_id' => 'old-run',
      'progress' => 500,
      'total' => 1000,
      'completed' => FALSE,
    ]);
    $state->set(EntityUsageQueueBatchManager::ENQUEUE_COMPLETED_AT_KEY, \Drupal::time()->getRequestTime());

    $queue = \Drupal::queue(EntityUsageQueueBatchManager::QUEUE_NAME);
    $queue->createItem(['entity_type' => 'node', 'entity_id' => 1, 'operation' => 'insert']);

    $manager->beginFreshEnqueueRun();

    $this->assertNull($state->get('mass_entity_usage.queue_progress.node'));
    $this->assertNull($state->get(EntityUsageQueueBatchManager::ENQUEUE_COMPLETED_AT_KEY));
    $this->assertNotEmpty($manager->getEnqueueRunId());
    $this->assertNotSame('old-run', $manager->getEnqueueRunId());
    $this->assertSame(0, $queue->numberOfItems());
  }

  /**
   * Tests interrupted progress is detected for the active run.
   */
  public function testHasInterruptedProgressForActiveRun(): void {
    $manager = $this->getManager();
    $run_id = 'test-run-active';
    \Drupal::state()->set(EntityUsageQueueBatchManager::ENQUEUE_RUN_ID_KEY, $run_id);
    \Drupal::state()->set('mass_entity_usage.queue_progress.paragraph', [
      'run_id' => $run_id,
      'progress' => 18000,
      'total' => 931774,
      'completed' => FALSE,
    ]);

    $this->assertTrue($manager->hasInterruptedProgress());
    $this->assertFalse($manager->wasEnqueueCompletedRecently());
  }

  /**
   * Tests completion within 24 hours blocks a new enqueue.
   */
  public function testWasEnqueueCompletedRecentlyWithinTtl(): void {
    $manager = $this->getManager();
    $manager->beginFreshEnqueueRun();
    $manager->markEnqueueCompleted();

    $this->assertTrue($manager->wasEnqueueCompletedRecently());
    $this->assertFalse($manager->hasInterruptedProgress());
  }

  /**
   * Tests completion older than 24 hours is not treated as recent.
   */
  public function testWasEnqueueCompletedRecentlyAfterTtl(): void {
    $manager = $this->getManager();
    $expired = \Drupal::time()->getRequestTime() - EntityUsageQueueBatchManager::ENQUEUE_COMPLETED_TTL - 1;
    \Drupal::state()->set(EntityUsageQueueBatchManager::ENQUEUE_COMPLETED_AT_KEY, $expired);

    $this->assertFalse($manager->wasEnqueueCompletedRecently());
  }

  /**
   * Tests resume skips entity types already completed in the current run.
   */
  public function testGenerateBatchSkipsCompletedTypesOnResume(): void {
    $manager = $this->getManager();
    $run_id = 'test-run-resume';
    $state = \Drupal::state();
    $state->set(EntityUsageQueueBatchManager::ENQUEUE_RUN_ID_KEY, $run_id);
    $state->set('mass_entity_usage.queue_progress.node', [
      'run_id' => $run_id,
      'progress' => 144692,
      'total' => 144692,
      'completed' => TRUE,
    ]);
    $state->set('mass_entity_usage.queue_progress.paragraph', [
      'run_id' => $run_id,
      'progress' => 18000,
      'total' => 931774,
      'completed' => FALSE,
    ]);

    $batch = $manager->generateBatch();
    $entity_types = array_map(static fn(array $operation): string => $operation[1][0], $batch['operations']);

    $this->assertSame(['paragraph'], $entity_types);
  }

  /**
   * Tests prepareResume attaches a run ID to legacy progress records.
   */
  public function testPrepareResumeAttachesRunIdToLegacyProgress(): void {
    $manager = $this->getManager();
    $state = \Drupal::state();
    $state->set(EntityUsageQueueBatchManager::ENQUEUE_RUN_ID_KEY, 'legacy-run');
    $state->set('mass_entity_usage.queue_progress.paragraph', [
      'progress' => 100,
      'total' => 200,
      'completed' => FALSE,
    ]);

    $manager->prepareResume();

    $progress = $state->get('mass_entity_usage.queue_progress.paragraph');
    $this->assertIsArray($progress);
    $this->assertSame('legacy-run', $progress['run_id']);
  }

  /**
   * Tests batchFinished sets the completion timestamp on success.
   */
  public function testBatchFinishedMarksEnqueueCompleted(): void {
    $manager = $this->getManager();
    $run_id = 'batch-finish-run';
    $state = \Drupal::state();
    $state->set(EntityUsageQueueBatchManager::ENQUEUE_RUN_ID_KEY, $run_id);
    $state->set('mass_entity_usage.queue_progress.node', [
      'run_id' => $run_id,
      'progress' => 1,
      'total' => 1,
      'completed' => TRUE,
    ]);
    $state->set('mass_entity_usage.queue_progress.paragraph', [
      'run_id' => $run_id,
      'progress' => 1,
      'total' => 1,
      'completed' => TRUE,
    ]);

    EntityUsageQueueBatchManager::batchFinished(TRUE, ['node', 'paragraph'], []);

    $this->assertNotNull($manager->getEnqueueCompletedAt());
    $this->assertTrue($manager->wasEnqueueCompletedRecently());
  }

  private function getManager(): EntityUsageQueueBatchManager {
    return \Drupal::service('mass_entity_usage.queue_batch_manager');
  }

  private function resetEnqueueState(): void {
    $manager = $this->getManager();
    $manager->beginFreshEnqueueRun();
    \Drupal::state()->delete(EntityUsageQueueBatchManager::ENQUEUE_COMPLETED_AT_KEY);
  }

}
