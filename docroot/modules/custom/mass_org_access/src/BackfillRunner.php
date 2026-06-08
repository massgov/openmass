<?php

namespace Drupal\mass_org_access;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\State\StateInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Resumable, logged backfill of field_content_organization.
 *
 * Walks every supported node bundle and media.document. Progress is
 * persisted in State so a Ctrl+C or crashed run can be resumed from the
 * last processed entity ID.
 */
class BackfillRunner {

  /**
   * Number of entities loaded and processed per batch.
   */
  const BATCH_SIZE = 100;

  /**
   * State keys for backfill progress.
   *
   * Single namespace, six entries — totals, last-processed id cursor,
   * and processed counter, separately for nodes and media.
   */
  private const STATE_KEY = 'mass_org_access.backfill';

  /**
   * Default log destination, in Drupal's private files stream.
   */
  public const DEFAULT_LOG_URI = 'private://mass_org_access/backfill.log';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly OrgAccessChecker $orgAccessChecker,
    private readonly StateInterface $state,
    private readonly FileSystemInterface $fileSystem,
    private readonly StageFileFetcher $stageFileFetcher,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ModuleExtensionList $moduleExtensionList,
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

    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;

    $log_uri = $log_uri ?: self::DEFAULT_LOG_URI;
    $this->prepareLogFile($log_uri, $output);
    $this->ensureMediaIconPlaceholders($output, $log_uri);

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

//    $this->processQueue('node', $progress, $output, $log_uri);
    $this->processQueue('media', $progress, $output, $log_uri);

    $this->log($output, $log_uri, sprintf(
      "=== Run completed %s ===\nNodes total: %d / %d (%d skipped)\nMedia total: %d / %d (%d skipped)",
      $this->now(),
      $progress['node_processed'], $progress['node_total'], $progress['node_skipped'],
      $progress['media_processed'], $progress['media_total'], $progress['media_skipped']
    ));
  }

  /**
   * Returns a site-aware timestamp for logging.
   *
   * Uses Drupal's configured timezone (not the server's). Format includes
   * TZ so log lines stay unambiguous across deployments.
   */
  private function now(): string {
    return (new DrupalDateTime())->format('Y-m-d H:i:s T');
  }

  /**
   * Ensures the log file's directory exists and is writable.
   *
   * Without this, subsequent appends silently swallow lines.
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
      'node_skipped' => 0,
      'media_skipped' => 0,
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
   * Wipes stored progress.
   *
   * The next run rescans from id 0 and recomputes totals.
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
        $progress[$entity_type . '_last_id'] = (int) $entity->id();
        $progress[$entity_type . '_processed']++;
        // One bad entity (e.g. a media item whose source file is missing both
        // locally and on the proxy origin) must never abort the whole run.
        // Log it and move on; the cursor has already advanced so a resume
        // skips it too.
        try {
          $this->backfillEntity($entity);
        }
        catch (\Throwable $e) {
          $progress[$entity_type . '_skipped']++;
          $this->log($output, $log_uri, sprintf(
            'SKIPPED %s:%d — %s: %s',
            $entity_type,
            $entity->id(),
            (new \ReflectionClass($e))->getShortName(),
            $e->getMessage()
          ));
        }
      }
      $this->saveProgress($progress);

      $this->log($output, $log_uri, sprintf(
        '%s: %d / %d processed, %d skipped (last %s %d)',
        ucfirst($entity_type),
        $progress[$entity_type . '_processed'],
        $progress[$entity_type . '_total'],
        $progress[$entity_type . '_skipped'],
        strtoupper($id_key),
        $progress[$entity_type . '_last_id']
      ));
    }
  }

  /**
   * Backfills one entity: its default revision and any forward draft.
   *
   * Edit access is checked against the latest revision, so a forward
   * (unpublished) draft left empty would lock its rightful editors out and
   * wipe the backfilled Permission Groups the moment the draft is published.
   * Both revisions are updated in place — only field_content_organization,
   * preserving the draft's content and pending state — so authors lose
   * nothing.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The default (loaded) revision of the entity to backfill.
   */
  public function backfillEntity(EntityInterface $entity): void {
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $this->populateRevision($entity, $storage);

    $latest_vid = $storage->getLatestRevisionId($entity->id());
    if ($latest_vid && (int) $latest_vid !== (int) $entity->getRevisionId()) {
      $draft = $storage->loadRevision($latest_vid);
      if ($draft) {
        $this->populateRevision($draft, $storage);
      }
    }
  }

  /**
   * Populates one revision's Permission Groups and saves it if it changed.
   *
   * Saves in place: no new revision, `setSyncing(TRUE)` so mass_validation
   * and the changed-timestamp stay out of the way. Skips the save entirely
   * when populate reports no change, to cut churn on already-correct or
   * unmappable entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $revision
   *   The revision to populate (default revision or a forward draft).
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The storage handler for the entity type.
   */
  private function populateRevision($revision, $storage): void {
    if (!$this->orgAccessChecker->populateOwnerGroupsFromOrganizations($revision)) {
      return;
    }
    // Saving media regenerates its thumbnail from the source file. On a
    // prod-copied database the file may be missing locally, which would throw
    // a FileNotExistsException — pull it from the origin first.
    $this->ensureMediaSourceLocal($revision);
    if (method_exists($revision, 'setNewRevision')) {
      $revision->setNewRevision(FALSE);
    }
    $revision->setSyncing(TRUE);
    $storage->save($revision);
  }

  /**
   * Creates placeholders for any missing media-icon thumbnails.
   *
   * Document/image media fall back to a generic icon stored under
   * media.settings:icon_base_uri (e.g. public://media-icons/generic/
   * document.png). Those icons live in the files directory, not in code, and
   * are not served by the stage_file_proxy origin — so on a prod-copied
   * database they are missing and every media resave logs a file_mdm error
   * while regenerating the thumbnail. Copy the media module's generic icon
   * over each referenced-but-missing icon so the fallback resolves cleanly.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Drush output for the summary line.
   * @param string $log_uri
   *   Log file URI.
   */
  private function ensureMediaIconPlaceholders(OutputInterface $output, string $log_uri): void {
    $icon_base = (string) $this->configFactory->get('media.settings')->get('icon_base_uri');
    if ($icon_base === '') {
      return;
    }
    $placeholder = $this->moduleExtensionList->getPath('media') . '/images/icons/generic.png';
    if (!is_file($placeholder)) {
      return;
    }
    $this->fileSystem->prepareDirectory($icon_base, FileSystemInterface::CREATE_DIRECTORY);

    $file_storage = $this->entityTypeManager->getStorage('file');
    $fids = $file_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uri', $icon_base . '/', 'STARTS_WITH')
      ->execute();

    $created = 0;
    foreach ($file_storage->loadMultiple($fids) as $file) {
      $uri = $file->getFileUri();
      $real = $this->fileSystem->realpath($uri);
      if ($real && file_exists($real)) {
        continue;
      }
      try {
        $this->fileSystem->copy($placeholder, $uri, FileExists::Replace);
        $created++;
      }
      catch (\Throwable $e) {
        // Best effort — a missing placeholder is non-fatal noise, not a stop.
      }
    }

    if ($created) {
      $this->log($output, $log_uri, sprintf(
        'Created %d missing media-icon placeholder(s) from the generic icon.',
        $created
      ));
    }
  }

  /**
   * Fetches a media entity's source file locally when it is missing.
   *
   * No-op for non-media entities, and a no-op on production or when
   * stage_file_proxy is unavailable (handled by the fetcher).
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The revision about to be saved.
   */
  private function ensureMediaSourceLocal(EntityInterface $entity): void {
    if (!$entity instanceof MediaInterface) {
      return;
    }
    $fid = $entity->getSource()->getSourceFieldValue($entity);
    if (!$fid) {
      return;
    }
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    if ($file) {
      $this->stageFileFetcher->ensureLocalCopy($file->getFileUri());
    }
  }

  /**
   * Builds the node query targeting bundles that carry the OOG field.
   *
   * Restricts the scan to bundles that actually have
   * field_content_organization (and excludes org_page — source of truth,
   * untouched by the backfill). Without this filter the backfill would
   * walk every node bundle on the site (page, executive_order, …) and
   * resave it needlessly.
   */
  private function buildNodeQuery() {
    $bundles = array_values(array_diff(
      $this->bundlesWithOogField('node'),
      ['org_page']
    ));
    return $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundles, 'IN');
  }

  /**
   * Builds the media query targeting bundles that carry the OOG field.
   */
  private function buildMediaQuery() {
    return $this->entityTypeManager->getStorage('media')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('bundle', $this->bundlesWithOogField('media'), 'IN');
  }

  /**
   * Bundles of $entity_type that have field_content_organization attached.
   */
  private function bundlesWithOogField(string $entity_type): array {
    $map = $this->entityFieldManager->getFieldMap();
    return array_keys($map[$entity_type]['field_content_organization']['bundles'] ?? []);
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
