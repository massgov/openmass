<?php

namespace Drupal\mass_org_access;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Resumable, logged backfill of field_content_organization across nodes
 * and media.document. Progress is persisted in State so a Ctrl+C or
 * crashed run can be resumed from the last processed entity ID.
 */
class BackfillRunner {

  /**
   * Number of entities loaded and processed per batch.
   */
  const BATCH_SIZE = 100;

  /**
   * State keys. Single namespace, six entries — totals, last-processed id
   * cursor, and processed counter, separately for nodes and media.
   */
  private const STATE_KEY = 'mass_org_access.backfill';

  /**
   * Default log destination, in Drupal's private files stream.
   */
  public const DEFAULT_LOG_URI = 'private://mass_org_access/backfill.log';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly OrgAccessChecker $orgAccessChecker,
    private readonly StateInterface $state,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * Runs (or resumes) the backfill until both queues are empty.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Drush output for live progress.
   * @param string|null $log_uri
   *   Stream-wrapper URI of the log file (e.g. private://moab.log). Appended
   *   to. NULL falls back to DEFAULT_LOG_URI.
   * @param bool $reset
   *   If TRUE, clears all stored progress and recomputes totals from scratch.
   */
  public function run(OutputInterface $output, ?string $log_uri = NULL, bool $reset = FALSE): void {
    $log_uri = $log_uri ?: self::DEFAULT_LOG_URI;
    $this->prepareLogFile($log_uri, $output);

    if ($reset) {
      $this->resetProgress();
      $this->log($output, $log_uri, 'Reset progress; starting from scratch.');
    }

    $progress = $this->loadProgress();
    $this->log($output, $log_uri, sprintf(
      "=== Run started %s ===\nNodes: %d / %d processed (last NID: %d)\nMedia: %d / %d processed (last MID: %d)",
      $this->now(),
      $progress['node_processed'], $progress['node_total'], $progress['node_last_id'],
      $progress['media_processed'], $progress['media_total'], $progress['media_last_id']
    ));

    $this->processQueue('node', $progress, $output, $log_uri);
    $this->processQueue('media', $progress, $output, $log_uri);

    $this->log($output, $log_uri, sprintf(
      "=== Run completed %s ===\nNodes total: %d / %d\nMedia total: %d / %d",
      $this->now(),
      $progress['node_processed'], $progress['node_total'],
      $progress['media_processed'], $progress['media_total']
    ));
  }

  /**
   * Site-aware timestamp for logging — uses Drupal's configured timezone
   * (not the server's). Format includes TZ so log lines stay unambiguous
   * across deployments.
   */
  private function now(): string {
    return (new DrupalDateTime())->format('Y-m-d H:i:s T');
  }

  /**
   * Ensures the log file's directory exists and is writable so subsequent
   * appends don't silently swallow lines.
   */
  private function prepareLogFile(string $log_uri, OutputInterface $output): void {
    $dir = dirname($log_uri);
    if (!$this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY)) {
      $output->writeln(sprintf('<error>Cannot create log directory %s; falling back to console only.</error>', $dir));
    }
  }

  /**
   * Loads counters from State, calculating totals on first run.
   */
  private function loadProgress(): array {
    $progress = $this->state->get(self::STATE_KEY) ?: [];
    $progress += [
      'node_total' => NULL,
      'media_total' => NULL,
      'node_processed' => 0,
      'media_processed' => 0,
      'node_last_id' => 0,
      'media_last_id' => 0,
    ];
    if ($progress['node_total'] === NULL) {
      $progress['node_total'] = (int) $this->buildNodeQuery()->count()->execute();
    }
    if ($progress['media_total'] === NULL) {
      $progress['media_total'] = (int) $this->buildMediaQuery()->count()->execute();
    }
    $this->saveProgress($progress);
    return $progress;
  }

  /**
   * Wipes stored progress so the next run rescans from id 0 and recomputes
   * totals.
   */
  public function resetProgress(): void {
    $this->state->delete(self::STATE_KEY);
  }

  private function saveProgress(array $progress): void {
    $this->state->set(self::STATE_KEY, $progress);
  }

  /**
   * Drains the queue for a single entity type, batch by batch.
   */
  private function processQueue(string $entity_type, array &$progress, OutputInterface $output, string $log_uri): void {
    $id_key = $entity_type === 'node' ? 'nid' : 'mid';
    $storage = $this->entityTypeManager->getStorage($entity_type);

    while (TRUE) {
      $query = $entity_type === 'node' ? $this->buildNodeQuery() : $this->buildMediaQuery();
      $ids = $query
        ->condition($id_key, $progress[$entity_type . '_last_id'], '>')
        ->sort($id_key)
        ->range(0, self::BATCH_SIZE)
        ->execute();
      if (empty($ids)) {
        break;
      }

      foreach ($storage->loadMultiple($ids) as $entity) {
        $this->orgAccessChecker->populateOwnerGroupsFromOrgPage($entity);
        if (method_exists($entity, 'setNewRevision')) {
          $entity->setNewRevision(FALSE);
        }
        $entity->setSyncing(TRUE);
        $storage->save($entity);
        $progress[$entity_type . '_last_id'] = (int) $entity->id();
        $progress[$entity_type . '_processed']++;
      }
      $this->saveProgress($progress);

      $this->log($output, $log_uri, sprintf(
        '%s: %d / %d processed (last %s %d)',
        ucfirst($entity_type),
        $progress[$entity_type . '_processed'],
        $progress[$entity_type . '_total'],
        strtoupper($id_key),
        $progress[$entity_type . '_last_id']
      ));
    }
  }

  /**
   * Node query: every supported bundle except org_page (source of truth,
   * populated manually by the content team).
   */
  private function buildNodeQuery() {
    return $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'org_page', '<>');
  }

  /**
   * Media query: only the document bundle has field_content_organization.
   */
  private function buildMediaQuery() {
    return $this->entityTypeManager->getStorage('media')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('bundle', 'document');
  }

  /**
   * Writes one line both to the log file and to the live drush output.
   */
  private function log(OutputInterface $output, string $log_uri, string $message): void {
    $line = '[' . $this->now() . '] ' . $message . PHP_EOL;
    $real = $this->fileSystem->realpath($log_uri) ?: $log_uri;
    @file_put_contents($real, $line, FILE_APPEND);
    $output->writeln($message);
  }

}
