<?php

namespace Drupal\mass_redirect_normalizer\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
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
    );
  }

  /**
   * Enqueues all eligible node and paragraph IDs for queue processing.
   *
   * @command mass-redirect-normalizer:normalize-links
   * @aliases mnrl
   * @option release-enqueue-lock Delete the enqueue sweep lock row from
   *   {semaphore} and exit (does not run a sweep).
   */
  public function normalizeRedirectLinks(
    $options = [
      'release-enqueue-lock' => FALSE,
    ],
  ): RowsOfFields {
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

    if (!empty($options['release-enqueue-lock'])) {
      $this->breakStaleEnqueueLock();
      $this->logNotice((string) dt('Released the enqueue sweep lock (clears stale rows left by crashed processes).'));
      return new RowsOfFields([]);
    }

    if (!$this->lock->acquire(self::ENQUEUE_LOCK_NAME, 3600)) {
      $this->logWarning((string) dt('Another redirect link normalization enqueue sweep is already running. Exiting. If no sweep is running (for example after a crash), run: drush mnrl --release-enqueue-lock'));
      return new RowsOfFields([]);
    }

    $enqueued = 0;
    try {
      foreach (RedirectLinkQueueEnqueuer::SUPPORTED_ENTITY_TYPES as $entityType) {
        $this->logNotice($entityType === 'node'
          ? (string) dt('Node phase: streaming published node IDs from ID 0 (chunked; no up-front ID load).')
          : (string) dt('Paragraph phase: streaming paragraph IDs from ID 0 (chunked).')
        );

        $phaseScanned = 0;
        foreach ($this->iterateSweepEntityIds($entityType) as $entityId) {
          $this->enqueuer->enqueueIdBulk($entityType, $entityId, 'drush');
          $phaseScanned++;
          $enqueued++;

          if ($phaseScanned % self::SWEEP_PROGRESS_BATCH === 0) {
            $this->logNotice((string) dt(
              'Progress (@type): scanned @scanned in this phase; enqueued @enqueued. Last @type:@id',
              [
                '@type' => $entityType,
                '@scanned' => $phaseScanned,
                '@enqueued' => $enqueued,
                '@id' => $entityId,
              ]
            ));
          }
        }

        $this->logNotice((string) dt(
          '@type phase finished this run: scanned @scanned; enqueued @enqueued.',
          [
            '@type' => $entityType,
            '@scanned' => $phaseScanned,
            '@enqueued' => $enqueued,
          ]
        ));
      }

      $this->enqueuer->flushEnqueueBuffers('drush');
      $this->logNotice((string) dt('ENQUEUE: completed scan; total enqueued entity refs @count.', [
        '@count' => $enqueued,
      ]));
    }
    finally {
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
   * Deletes the enqueue sweep lock row from {semaphore}.
   */
  private function breakStaleEnqueueLock(): void {
    $this->database->delete('semaphore')
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
