<?php

namespace Drupal\mass_redirect_normalizer\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer;
use Drush\Commands\DrushCommands;
use Psr\Container\ContainerInterface;

/**
 * Drush command for redirect link normalization.
 */
final class MassRedirectNormalizerCommands extends DrushCommands {

  private const ENQUEUE_LOCK_NAME = 'mass_redirect_normalizer.enqueue';
  private const SWEEP_ID_CHUNK = 2000;
  private const SWEEP_PROGRESS_BATCH = 500;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RedirectLinkQueueEnqueuer $enqueuer,
    protected LockBackendInterface $lock,
    protected Connection $database,
    protected StateInterface $state,
  ) {
    parent::__construct();
  }

  /**
   * Instantiates this command from the Drupal container after bootstrap.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('mass_redirect_normalizer.enqueuer'),
      $container->get('lock'),
      $container->get('database'),
      $container->get('state'),
    );
  }

  /**
   * Enqueues all eligible node and paragraph IDs for queue processing.
   *
   * Safe to rerun after failure: once the lock is available, empties the
   * normalization queue, then runs a full ID sweep from the beginning.
   *
   * @command mass-redirect-normalizer:normalize-links
   * @aliases mnrl
   * @option force-release-lock
   *   Force-release the enqueue lock before attempting this run.
   */
  public function normalizeRedirectLinks(array $options = ['force-release-lock' => FALSE]): RowsOfFields {
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

    if (!empty($options['force-release-lock'])) {
      $released = $this->forceReleaseEnqueueLock();
      if ($released > 0) {
        $this->logWarning(sprintf(
          'Force-released %d enqueue lock row(s) before starting this run.',
          $released
        ));
      }
    }

    if (!$this->lock->acquire(self::ENQUEUE_LOCK_NAME, 3600)) {
      $this->logWarning('Could not acquire the redirect link normalization enqueue lock. Try again in a moment.');
      return new RowsOfFields([]);
    }

    $enqueued = 0;
    try {
      $this->state->set(RedirectLinkQueueEnqueuer::SWEEP_IN_PROGRESS_STATE_KEY, time());
      $cleared = $this->enqueuer->purgeNormalizationQueue();
      if ($cleared > 0) {
        $this->logNotice(sprintf(
          'Cleared %d pending normalization queue item(s) before starting a fresh enqueue sweep.',
          $cleared
        ));
      }

      foreach (RedirectLinkQueueEnqueuer::SUPPORTED_ENTITY_TYPES as $entityType) {
        $this->logNotice($entityType === 'node'
          ? 'Node phase: streaming published node IDs from ID 0 (chunked; no up-front ID load).'
          : 'Paragraph phase: streaming paragraph IDs from ID 0 (chunked).'
        );

        $phaseScanned = 0;
        foreach ($this->iterateSweepEntityIds($entityType) as $entityId) {
          $this->enqueuer->enqueueIdBulk($entityType, $entityId, 'drush');
          $phaseScanned++;
          $enqueued++;

          if ($phaseScanned % self::SWEEP_PROGRESS_BATCH === 0) {
            $this->logNotice(sprintf(
              'Progress (%s): scanned %d in this phase; enqueued %d. Last %s:%d',
              $entityType,
              $phaseScanned,
              $enqueued,
              $entityType,
              $entityId
            ));
          }
        }

        $this->logNotice(sprintf(
          '%s phase finished this run: scanned %d; enqueued %d.',
          $entityType,
          $phaseScanned,
          $enqueued
        ));
      }

      $this->enqueuer->flushEnqueueBuffers('drush');
      $this->logNotice(sprintf(
        'ENQUEUE: completed scan; total enqueued entity refs %d.',
        $enqueued
      ));
    }
    finally {
      $this->state->delete(RedirectLinkQueueEnqueuer::SWEEP_IN_PROGRESS_STATE_KEY);
      $this->enqueuer->flushEnqueueBuffers('drush');
      $this->lock->release(self::ENQUEUE_LOCK_NAME);
    }

    return new RowsOfFields([]);
  }

  /**
   * Yields entity IDs without loading all IDs into memory.
   *
   * @return \Generator<int, int, mixed, void>
   *   Yields numeric entity IDs in ascending order.
   */
  private function iterateSweepEntityIds(string $entityType): \Generator {
    $idField = $entityType === 'node' ? 'nid' : 'id';
    $cursor = 0;
    do {
      $query = $this->entityTypeManager
        ->getStorage($entityType)
        ->getQuery()
        ->accessCheck(FALSE)
        ->sort($idField, 'ASC')
        ->condition($idField, $cursor, '>')
        ->range(0, self::SWEEP_ID_CHUNK);

      if ($entityType === 'node') {
        $query->condition('status', 1);
      }

      $ids = array_values($query->execute());
      foreach ($ids as $id) {
        $id = (int) $id;
        $cursor = $id;
        yield $id;
      }
    } while ($ids !== []);
  }

  /**
   * Force-deletes enqueue lock row(s) from {semaphore}.
   */
  private function forceReleaseEnqueueLock(): int {
    return (int) $this->database->delete('semaphore')
      ->condition('name', self::ENQUEUE_LOCK_NAME)
      ->execute();
  }

  /**
   * Logs a notice only when a logger is available.
   */
  private function logNotice(string $message): void {
    $logger = $this->logger();
    if ($logger) {
      $logger->notice($message);
    }
  }

  /**
   * Logs a warning only when a logger is available.
   */
  private function logWarning(string $message): void {
    $logger = $this->logger();
    if ($logger) {
      $logger->warning($message);
    }
  }

}
