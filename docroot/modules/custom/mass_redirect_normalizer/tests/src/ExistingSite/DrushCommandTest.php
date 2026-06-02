<?php

namespace Drupal\Tests\mass_redirect_normalizer\ExistingSite;

use Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests the mnrl Drush command (enqueue, lock, purge behaviour).
 *
 * @group existing-site
 */
class DrushCommandTest extends MassExistingSiteBase {

  use RedirectNormalizerTestTrait;

  /**
   * Tests command bundle filter constrains output.
   */
  public function testCommandBundleFiltering(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
  }

  /**
   * Tests command skips unpublished nodes.
   */
  public function testCommandSkipsUnpublishedNode(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
  }

  /**
   * Tests command writes CSV report rows for parseable output.
   */
  public function testCommandWritesCsvReport(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
  }

  /**
   * Tests CSV output appends new rows when the report file already exists.
   */
  public function testCommandAppendsToExistingCsvReport(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
  }

  /**
   * Tests CSV report includes entity-reference change rows.
   */
  public function testCommandCsvIncludesEntityReferenceRows(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
  }

  /**
   * Tests command kinds filter returns only selected change types.
   */
  public function testCommandKindsFilterReturnsOnlyEntityReferences(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
  }

  /**
   * Tests command progress checkpoint resume and show-progress behavior.
   */
  public function testCommandProgressResumeAndShowProgress(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
  }

  /**
   * Tests execute mode enqueues work without saving inline.
   */
  public function testCommandExecuteEnqueuesWithoutSavingInline(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
  }

  /**
   * Tests duplicate enqueue attempts do not multiply queue items.
   */
  public function testDuplicateEnqueueDedupesQueueItems(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
  }

  /**
   * Tests execute mode auto-resumes enqueue checkpoint.
   */
  public function testCommandExecuteAutoResumesEnqueueCheckpoint(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
  }

  /**
   * Tests enqueue is blocked while a lock is held.
   */
  public function testCommandEnqueueBlockedWhileLockHeld(): void {
    $this->markTestSkipped('Covered by new lock behavior tests.');
  }

  /**
   * Tests mnrl clears the normalization queue before a fresh enqueue sweep.
   */
  public function testMnrlPurgesNormalizationQueueBeforeEnqueue(): void {
    /** @var \Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer $enqueuer */
    $enqueuer = \Drupal::service('mass_redirect_normalizer.enqueuer');
    $enqueuer->purgeNormalizationQueue();

    $queue = \Drupal::queue(RedirectLinkQueueEnqueuer::QUEUE_NAME);
    $queue->createItem(['entity_type' => 'node', 'entity_id' => 1, 'source' => 'presave']);
    $queue->createItem(['entity_type' => 'node', 'entity_id' => 2, 'source' => 'presave']);
    $this->assertSame(2, $queue->numberOfItems());

    $cleared = $enqueuer->purgeNormalizationQueue();
    $this->assertSame(2, $cleared);
    $this->assertSame(0, $queue->numberOfItems());
  }

  /**
   * Tests --force-release-lock clears a stale sweep lock row.
   */
  public function testReleaseEnqueueLockClearsStaleSweepLock(): void {
    $database = \Drupal::database();
    $database->delete('semaphore')
      ->condition('name', 'mass_redirect_normalizer.enqueue')
      ->execute();
    $database->insert('semaphore')
      ->fields([
        'name' => 'mass_redirect_normalizer.enqueue',
        'value' => 'stale-lock-value-not-current-request',
        'expire' => microtime(TRUE) + 3600,
      ])
      ->execute();

    $command = $this->createNormalizerCommand();
    $method = new \ReflectionMethod($command, 'forceReleaseEnqueueLock');
    $method->setAccessible(TRUE);
    $released = (int) $method->invoke($command);
    $this->assertSame(1, $released);

    $count = (int) $database->select('semaphore', 's')
      ->condition('name', 'mass_redirect_normalizer.enqueue')
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertSame(0, $count);

    $lock = \Drupal::lock();
    $this->assertTrue($lock->acquire('mass_redirect_normalizer.enqueue', 3600));
    $lock->release('mass_redirect_normalizer.enqueue');
  }

  /**
   * Tests simulate mode does not enqueue queue items.
   */
  public function testSimulateDoesNotEnqueue(): void {
    $this->markTestSkipped('Removed by enqueue-only command refactor.');
  }

}
