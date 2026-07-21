<?php

namespace Drupal\Tests\mass_redirect_normalizer\ExistingSite;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
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
   * Tests enqueue is rejected while the sweep lock is already held.
   */
  public function testCommandEnqueueBlockedWhileLockHeld(): void {
    \Drupal::state()->delete(RedirectLinkQueueEnqueuer::SWEEP_IN_PROGRESS_STATE_KEY);
    $this->purgeNormalizationQueue();
    $queue = \Drupal::queue(RedirectLinkQueueEnqueuer::QUEUE_NAME);
    $queue->createItem(['entity_type' => 'node', 'entity_id' => 12345, 'source' => 'presave']);
    $this->assertSame(1, $queue->numberOfItems());

    $database = \Drupal::database();
    $database->delete('semaphore')
      ->condition('name', 'mass_redirect_normalizer.enqueue')
      ->execute();
    $database->insert('semaphore')
      ->fields([
        'name' => 'mass_redirect_normalizer.enqueue',
        'value' => 'held-by-another-sweep',
        'expire' => microtime(TRUE) + 3600,
      ])
      ->execute();

    $command = $this->createNormalizerCommand();
    $result = $command->normalizeRedirectLinks();
    $this->assertInstanceOf(RowsOfFields::class, $result);
    $this->assertSame(1, $queue->numberOfItems(), 'Queue must not be purged when the lock is unavailable.');
    $this->assertNull(
      \Drupal::state()->get(RedirectLinkQueueEnqueuer::SWEEP_IN_PROGRESS_STATE_KEY),
      'Sweep state must not be set when enqueue is rejected.'
    );

    $database->delete('semaphore')
      ->condition('name', 'mass_redirect_normalizer.enqueue')
      ->execute();
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
   * Tests the sweep ID query excludes unpublished nodes.
   */
  public function testCommandSweepQueryExcludesUnpublishedNodes(): void {
    $unpublished = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 0,
      'moderation_state' => 'draft',
    ]);

    $matching = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('nid', (int) $unpublished->id())
      ->execute();

    $this->assertEmpty($matching);
  }

}
