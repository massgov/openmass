<?php

namespace Drupal\mass_redirect_normalizer\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\mass_redirect_normalizer\RedirectLinkNormalizationEligibility;
use Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager;
use Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer;
use Drupal\mayflower\Helper;
use Drupal\paragraphs\Entity\Paragraph;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Psr\Container\ContainerInterface;

/**
 * Drush command for redirect link normalization.
 */
final class MassRedirectNormalizerCommands extends DrushCommands {

  private const PROGRESS_STATE_KEY = 'mass_redirect_normalizer.command_progress';

  private const ENQUEUE_LOCK_NAME = 'mass_redirect_normalizer.enqueue';

  /**
   * Batch size for loadMultiple, progress notices, and resume checkpoints (fast path).
   */
  private const SWEEP_BATCH = 500;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RedirectLinkNormalizationManager $normalizerManager,
    protected StateInterface $state,
    protected RedirectLinkQueueEnqueuer $enqueuer,
    protected RedirectLinkNormalizationEligibility $eligibility,
    protected LockBackendInterface $lock,
    protected Connection $database,
  ) {
    parent::__construct();
  }

  /**
   * Instantiates this command from the Drupal container after bootstrap.
   *
   * AutowireTrait cannot resolve interface type hints to Drupal service IDs, so
   * dependencies are wired explicitly.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('mass_redirect_normalizer.manager'),
      $container->get('state'),
      $container->get('mass_redirect_normalizer.enqueuer'),
      $container->get('mass_redirect_normalizer.eligibility'),
      $container->get('lock'),
      $container->get('database'),
    );
  }

  /**
   * Normalizes redirect-based links in nodes and paragraphs.
   *
   * Use --simulate (or global `drush --simulate`) to preview changes only.
   * Without simulation, entities are enqueued by ID; the queue worker loads each
   * entity and applies eligibility before normalizing.
   *
   * @command mass-redirect-normalizer:normalize-links
   * @field-labels
   *   status: Status
   *   entity_type: Entity Type
   *   entity_id: Entity ID
   *   parent_node_id: Parent Node ID
   *   bundle: Bundle
   *   field: Field
   *   delta: Delta
   *   kind: Kind
   *   before: URL before
   *   after: URL after
   *   details: Details
   * @default-fields status,entity_type,entity_id,parent_node_id,bundle,field,before,after
   * @aliases mnrl
   * @option limit Max eligible entities to process total (0 = no limit).
   * @option bundle Limit to this bundle / paragraph type.
   * @option entity-ids Comma-separated IDs to process only. IDs are checked
   *   against both node and paragraph entities. Ignores --limit.
   * @option simulate Dry-run: show diffs only; do not save (same as global `drush --simulate`).
   * @option csv-path Optional path to write a CSV report. Works in simulate and
   *   execute. In execute mode, a dry-run is run to build the same diff rows
   *   (adds work; use plain enqueue without this for maximum speed). If the file
   *   already exists and is non-empty, new rows are appended (header is not repeated).
   * @option kinds Comma-separated change kinds to include: text,link,entity_reference.
   *   In execute mode, only entities with at least one matching change are
   *   enqueued (requires a dry-run per entity).
   * @option entity-type Limit processing to node or paragraph (default: both).
   * @option start-id Start scanning at this numeric entity ID (inclusive).
   * @option resume Continue from saved checkpoint.
   * @option show-progress Print saved checkpoint and exit.
   * @option reset-progress Clear saved checkpoint before running.
   * @option release-enqueue-lock Delete the enqueue sweep lock row from
   *   {semaphore} and exit (does not run a sweep). Use after a crash or kill when
   *   PHP could not release the lock; LockBackendInterface::release() only clears
   *   locks owned by the current request.
   * @usage mass-redirect-normalizer:normalize-links --simulate --limit=100
   *   Preview changes. Use --format=json for machine-readable output.
   */
  public function normalizeRedirectLinks(
    $options = [
      'limit' => 0,
      'bundle' => NULL,
      'entity-ids' => NULL,
      'simulate' => FALSE,
      'csv-path' => NULL,
      'kinds' => NULL,
      'entity-type' => NULL,
      'start-id' => 0,
      'resume' => FALSE,
      'show-progress' => FALSE,
      'reset-progress' => FALSE,
      'release-enqueue-lock' => FALSE,
    ],
  ): RowsOfFields {
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
    $entityTypes = $this->parseEntityTypesOption((string) ($options['entity-type'] ?? ''));
    $limit = max(0, (int) ($options['limit'] ?? 0));
    $startId = max(0, (int) ($options['start-id'] ?? 0));
    $entityIdsOption = isset($options['entity-ids']) ? trim((string) $options['entity-ids']) : '';
    $showProgress = !empty($options['show-progress']);
    $resetProgress = !empty($options['reset-progress']);
    try {
      $simulate = !empty($options['simulate']) || Drush::simulate();
    }
    catch (\RuntimeException) {
      // Allow PHPUnit to call this command without full Drush bootstrap.
      $simulate = !empty($options['simulate']);
    }
    $resume = !$simulate || !empty($options['resume']);
    $rows = [];
    $csvRows = [];
    $processed = 0;
    $runProcessed = 0;
    $entitiesChanged = 0;
    $valueUpdates = 0;
    $enqueued = 0;
    $alreadyQueued = 0;
    $skippedIneligible = 0;
    $nodePublishedCache = [];
    $newerDraftCache = [];
    $kindsFilter = $this->parseKindsFilter(isset($options['kinds']) ? (string) $options['kinds'] : '');
    $csvPathOption = isset($options['csv-path']) ? trim((string) $options['csv-path']) : '';
    $executeUsesDryRun = !$simulate && ($kindsFilter !== [] || $csvPathOption !== '');
    if ($resetProgress) {
      $this->clearProgressState();
      if ($this->logger()) {
        $this->logger()->notice((string) dt('Cleared saved progress checkpoint.'));
      }
    }
    if ($showProgress) {
      $this->logSavedProgress();
      return new RowsOfFields([]);
    }

    if (!empty($options['release-enqueue-lock'])) {
      $this->breakStaleEnqueueLock();
      if ($this->logger()) {
        $this->logger()->notice((string) dt('Released the enqueue sweep lock (clears stale rows left by crashed processes).'));
      }
      return new RowsOfFields([]);
    }

    $saved = $this->state->get(self::PROGRESS_STATE_KEY, []);
    $lastIds = (is_array($saved) && isset($saved['last_ids']) && is_array($saved['last_ids'])) ? $saved['last_ids'] : [];
    if ($resume && is_array($saved)) {
      // Keep counters running when we resume.
      $processed = max(0, (int) ($saved['processed'] ?? 0));
      if ($simulate) {
        $entitiesChanged = max(0, (int) ($saved['updated_entities'] ?? 0));
        $valueUpdates = max(0, (int) ($saved['changed_field_values'] ?? 0));
      }
      else {
        $enqueued = max(0, (int) ($saved['updated_entities'] ?? 0));
      }
    }
    $hasCheckpoint = $this->savedCheckpointHasData($saved, $lastIds);
    if ($resume && $hasCheckpoint && $this->logger()) {
      $this->logger()->notice((string) dt('Continuing from saved checkpoint.'));
    }

    $sweepTotal = $this->estimateSweepTotal($entityTypes, $options, $entityIdsOption, $startId, $resume, $lastIds, $limit);

    $lockAcquired = FALSE;
    if (!$simulate) {
      if (!$this->lock->acquire(self::ENQUEUE_LOCK_NAME, 3600)) {
        if ($this->logger()) {
          $this->logger()->warning((string) dt('Another redirect link normalization enqueue sweep is already running. Exiting. If no sweep is running (for example after a crash), run: drush mnrl --release-enqueue-lock'));
        }
        return new RowsOfFields([]);
      }
      $lockAcquired = TRUE;
    }

    try {
      foreach ($entityTypes as $entityType) {
        $effectiveStartId = $this->effectiveStartIdForEntityType($entityType, $startId, $resume, $lastIds);
        if ($entityIdsOption !== '') {
          $ids = $this->parseCommaSeparatedEntityIds($entityIdsOption, $effectiveStartId);
        }
        else {
          $query = $this->buildSweepEntityQuery($entityType, $options, $entityIdsOption, $effectiveStartId);
          if ($limit > 0) {
            $query->range(0, $limit);
          }
          $ids = $query->execute();
        }

        $idList = array_values($ids);
        if (!empty($options['bundle']) && $entityIdsOption !== '') {
          $idList = $this->filterIdsByBundle($entityType, $idList, (string) $options['bundle']);
        }
        $idListCount = count($idList);

        // Fast enqueue: push IDs only; worker loads entities and applies eligibility.
        if (!$simulate && !$executeUsesDryRun) {
          $remainingInBatch = $idListCount;
          $fastIterations = 0;
          foreach ($idList as $id) {
            if ($limit > 0 && $runProcessed >= $limit) {
              break 2;
            }

            $enqueueResult = $this->enqueuer->enqueueId($entityType, (int) $id, 'drush');
            $fastIterations++;
            $processed++;
            $runProcessed++;
            if ($enqueueResult === 'enqueued') {
              $enqueued++;
            }
            elseif ($enqueueResult === 'already_queued') {
              $alreadyQueued++;
            }
            else {
              $skippedIneligible++;
            }

            if ($this->logger() && $runProcessed % self::SWEEP_BATCH === 0) {
              $this->logger()->notice($this->buildSweepProgressNotice(
                'Enqueue',
                $runProcessed,
                $sweepTotal,
                $processed,
                [
                  '@enqueued' => $enqueued,
                  '@already' => $alreadyQueued,
                  '@skipped' => $skippedIneligible,
                  '@type' => $entityType,
                  '@id' => $id,
                ],
              ));
            }
            $remainingInBatch--;
            $shouldCheckpoint = ($remainingInBatch === 0)
              || ($fastIterations % self::SWEEP_BATCH === 0)
              || ($limit > 0 && $runProcessed >= $limit);
            if ($shouldCheckpoint) {
              $this->saveProgressState($processed, $enqueued, $valueUpdates, $entityType, (int) $id, $simulate, FALSE);
            }
          }
          $this->enqueuer->flushPendingDedupeState();
          continue;
        }

        $storage = $this->entityTypeManager->getStorage($entityType);
        for ($offset = 0; $offset < $idListCount; $offset += self::SWEEP_BATCH) {
          $chunkIds = array_slice($idList, $offset, self::SWEEP_BATCH);
          $entities = $storage->loadMultiple($chunkIds);
          foreach ($chunkIds as $id) {
            if ($limit > 0 && $runProcessed >= $limit) {
              break 3;
            }

            $entity = $entities[$id] ?? NULL;
            if (!$entity) {
              continue;
            }

            if (!empty($options['bundle']) && $entity->bundle() !== $options['bundle']) {
              continue;
            }

            if (!$this->eligibility->isEligible($entityType, $entity, $nodePublishedCache, $newerDraftCache)) {
              continue;
            }

            if ($simulate) {
              $result = $this->normalizerManager->normalizeEntity($entity, FALSE, TRUE);
              $processed++;
              $runProcessed++;
              if ($this->logger() && $runProcessed % self::SWEEP_BATCH === 0) {
                $this->logger()->notice($this->buildSweepProgressNotice(
                'Simulation',
                $runProcessed,
                $sweepTotal,
                $processed,
                [
                  '@updated' => $entitiesChanged,
                  '@diffs' => $valueUpdates,
                  '@type' => $entityType,
                  '@id' => $id,
                ],
                ));
              }
              $this->saveProgressState($processed, $entitiesChanged, $valueUpdates, $entityType, (int) $id, $simulate, FALSE);
              if (!empty($result['changed'])) {
                $changes = $this->filterChangesByKinds($result['changes'] ?? [], $kindsFilter);
                if ($changes === []) {
                  continue;
                }
                $entitiesChanged++;
                $valueUpdates += $this->appendDiffRows($rows, $csvRows, $entityType, (int) $id, $entity, $changes, 'would_update', 'dry-run');
              }
              continue;
            }

            if ($executeUsesDryRun) {
              $result = $this->normalizerManager->normalizeEntity($entity, FALSE, TRUE);
              $changes = $this->filterChangesByKinds($result['changes'] ?? [], $kindsFilter);
              if ($changes === []) {
                $processed++;
                $runProcessed++;
                if ($this->logger() && $runProcessed % self::SWEEP_BATCH === 0) {
                  $this->logger()->notice($this->buildSweepProgressNotice(
                  'Enqueue',
                  $runProcessed,
                  $sweepTotal,
                  $processed,
                  [
                    '@enqueued' => $enqueued,
                    '@already' => $alreadyQueued,
                    '@skipped' => $skippedIneligible,
                    '@type' => $entityType,
                    '@id' => $id,
                  ],
                  ));
                }
                $this->saveProgressState($processed, $enqueued, $valueUpdates, $entityType, (int) $id, $simulate, FALSE);
                continue;
              }
              $valueUpdates += $this->appendDiffRows($rows, $csvRows, $entityType, (int) $id, $entity, $changes, 'enqueued', 'queued');
            }

            // Slow execute path implies --kinds or --csv-path (dry-run per entity).
            $enqueueResult = $this->enqueuer->enqueueVerified($entity, 'drush');
            $processed++;
            $runProcessed++;
            if ($enqueueResult === 'enqueued') {
              $enqueued++;
            }
            elseif ($enqueueResult === 'already_queued') {
              $alreadyQueued++;
            }
            else {
              $skippedIneligible++;
            }

            if ($this->logger() && $runProcessed % self::SWEEP_BATCH === 0) {
              $this->logger()->notice($this->buildSweepProgressNotice(
              'Enqueue',
              $runProcessed,
              $sweepTotal,
              $processed,
              [
                '@enqueued' => $enqueued,
                '@already' => $alreadyQueued,
                '@skipped' => $skippedIneligible,
                '@type' => $entityType,
                '@id' => $id,
              ],
              ));
            }
            $this->saveProgressState($processed, $enqueued, $valueUpdates, $entityType, (int) $id, $simulate, FALSE);
          }
        }
      }
    }
    finally {
      if ($lockAcquired) {
        $this->lock->release(self::ENQUEUE_LOCK_NAME);
      }
    }

    if ($simulate) {
      $mode = 'SIMULATION';
      if ($this->logger()) {
        $limitText = $limit > 0 ? (string) $limit : 'none';
        $this->logger()->notice((string) dt('@mode: processed @count entities (limit: @limit); updated entities: @updated; changed field values: @diffs.', [
          '@mode' => $mode,
          '@count' => $processed,
          '@limit' => $limitText,
          '@updated' => $entitiesChanged,
          '@diffs' => $valueUpdates,
        ]));
      }
      $this->saveProgressState($processed, $entitiesChanged, $valueUpdates, NULL, NULL, $simulate, TRUE);
    }
    else {
      if ($this->logger()) {
        $limitText = $limit > 0 ? (string) $limit : 'none';
        $enqueueNotice = (string) dt('ENQUEUE: scanned @count entities (limit: @limit); enqueued @enqueued; already queued @already; skipped @skipped.', [
          '@count' => $processed,
          '@limit' => $limitText,
          '@enqueued' => $enqueued,
          '@already' => $alreadyQueued,
          '@skipped' => $skippedIneligible,
        ]);
        if ($executeUsesDryRun && $valueUpdates > 0) {
          $enqueueNotice .= ' ' . (string) dt('Report rows (field-level diffs): @diffs.', [
            '@diffs' => $valueUpdates,
          ]);
        }
        $this->logger()->notice($enqueueNotice);
      }
      $this->saveProgressState($processed, $enqueued, $valueUpdates, NULL, NULL, $simulate, TRUE);
    }

    if ($csvPathOption !== '') {
      $csvAppended = $this->writeCsvReport($csvPathOption, $csvRows);
      if ($this->logger()) {
        $count = count($csvRows);
        if ($csvAppended) {
          $this->logger()->notice((string) dt('CSV report appended to @path (@count new row(s)).', [
            '@path' => $csvPathOption,
            '@count' => $count,
          ]));
        }
        else {
          $this->logger()->notice((string) dt('CSV report written to @path with @count row(s).', [
            '@path' => $csvPathOption,
            '@count' => $count,
          ]));
        }
      }
    }

    return new RowsOfFields($rows);
  }

  /**
   * Appends table and CSV rows for field-level normalization changes.
   *
   * @return int
   *   Number of rows appended.
   */
  private function appendDiffRows(
    array &$rows,
    array &$csvRows,
    string $entityType,
    int $entityId,
    object $entity,
    array $changes,
    string $status,
    string $details,
  ): int {
    $parentNodeId = '-';
    if ($entityType === 'paragraph' && $entity instanceof Paragraph) {
      $parentNode = Helper::getParentNode($entity);
      $parentNodeId = $parentNode ? (string) $parentNode->id() : '-';
    }
    $count = 0;
    foreach ($changes as $change) {
      [$beforePreview, $afterPreview] = $this->buildUrlBeforeAfter(
        (string) $change['kind'],
        (string) $change['before'],
        (string) $change['after'],
        TRUE,
      );
      [$beforeRaw, $afterRaw] = $this->buildUrlBeforeAfter(
        (string) $change['kind'],
        (string) $change['before'],
        (string) $change['after'],
        FALSE,
      );
      $row = [
        'status' => $status,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'parent_node_id' => $parentNodeId,
        'bundle' => $entity->bundle(),
        'field' => $change['field'],
        'delta' => (string) $change['delta'],
        'kind' => $change['kind'],
        'before' => $beforePreview,
        'after' => $afterPreview,
        'details' => $details,
      ];
      $rows[] = $row;
      $csvRows[] = [
        'status' => $row['status'],
        'entity_type' => $row['entity_type'],
        'entity_id' => $row['entity_id'],
        'parent_node_id' => $row['parent_node_id'],
        'bundle' => $row['bundle'],
        'field' => $row['field'],
        'delta' => $row['delta'],
        'kind' => $row['kind'],
        'before' => $beforeRaw,
        'after' => $afterRaw,
        'details' => $row['details'],
      ];
      $count++;
    }
    return $count;
  }

  /**
   * Builds URL-only before/after values for table output.
   *
   * Link fields show a readable URI/path.
   * Text fields show changed href values in document order.
   */
  private function buildUrlBeforeAfter(string $kind, string $before, string $after, bool $truncate = TRUE): array {
    $max = 120;
    if ($kind === 'entity_reference') {
      if (!$truncate) {
        return [$before, $after];
      }
      return [
        $this->truncateForTable($before, $max),
        $this->truncateForTable($after, $max),
      ];
    }
    if ($kind === 'link') {
      $beforeText = $this->formatUriForDisplay($before);
      $afterText = $this->formatUriForDisplay($after);
      if (!$truncate) {
        return [$beforeText, $afterText];
      }
      return [
        $this->truncateForTable($beforeText, $max),
        $this->truncateForTable($afterText, $max),
      ];
    }

    $beforeHrefs = $this->extractAnchorHrefs($before);
    $afterHrefs = $this->extractAnchorHrefs($after);
    $pairs = [];
    $count = max(count($beforeHrefs), count($afterHrefs));
    for ($i = 0; $i < $count; $i++) {
      $b = $beforeHrefs[$i] ?? '';
      $a = $afterHrefs[$i] ?? '';
      if ($b !== $a) {
        $pairs[] = [$b, $a];
      }
    }

    if ($pairs === []) {
      if ($beforeHrefs !== [] || $afterHrefs !== []) {
        if (!$truncate) {
          return [$beforeHrefs[0] ?? '-', $afterHrefs[0] ?? '-'];
        }
        return [
          $this->truncateForTable($beforeHrefs[0] ?? '-', $max),
          $this->truncateForTable($afterHrefs[0] ?? '-', $max),
        ];
      }
      return ['-', '-'];
    }

    $beforeUrls = implode('; ', array_column($pairs, 0));
    $afterUrls = implode('; ', array_column($pairs, 1));
    if (!$truncate) {
      return [$beforeUrls, $afterUrls];
    }
    return [
      $this->truncateForTable($beforeUrls, $max),
      $this->truncateForTable($afterUrls, $max),
    ];
  }

  /**
   * Writes report rows to a CSV file; appends data rows if file already has content.
   *
   * @return bool
   *   TRUE when rows were appended to an existing non-empty file.
   */
  private function writeCsvReport(string $path, array $rows): bool {
    $directory = dirname($path);
    if (!is_dir($directory) && !@mkdir($directory, 0775, TRUE) && !is_dir($directory)) {
      throw new \RuntimeException(sprintf('Could not create CSV directory: %s', $directory));
    }

    $appendDataOnly = is_file($path) && (int) filesize($path) > 0;
    $handle = @fopen($path, $appendDataOnly ? 'ab' : 'wb');
    if ($handle === FALSE) {
      throw new \RuntimeException(sprintf('Could not open CSV path for writing: %s', $path));
    }

    $header = [
      'status',
      'entity_type',
      'entity_id',
      'parent_node_id',
      'bundle',
      'field',
      'delta',
      'kind',
      'before',
      'after',
      'details',
    ];
    if (!$appendDataOnly) {
      fputcsv($handle, $header);
    }
    foreach ($rows as $row) {
      fputcsv($handle, [
        $row['status'] ?? '',
        $row['entity_type'] ?? '',
        $row['entity_id'] ?? '',
        $row['parent_node_id'] ?? '',
        $row['bundle'] ?? '',
        $row['field'] ?? '',
        $row['delta'] ?? '',
        $row['kind'] ?? '',
        $row['before'] ?? '',
        $row['after'] ?? '',
        $row['details'] ?? '',
      ]);
    }
    fclose($handle);

    return $appendDataOnly;
  }

  /**
   * Lists href attribute values for anchors in document order.
   *
   * @return array
   *   A list of href strings.
   */
  private function extractAnchorHrefs(string $html): array {
    if ($html === '') {
      return [];
    }
    $dom = Html::load($html);
    $xpath = new \DOMXPath($dom);
    $hrefs = [];
    foreach ($xpath->query('//a[@href]') as $anchor) {
      if ($anchor instanceof \DOMElement) {
        $hrefs[] = (string) $anchor->getAttribute('href');
      }
    }
    return $hrefs;
  }

  /**
   * Formats link-field URIs for CLI output.
   */
  private function formatUriForDisplay(string $uri): string {
    $uri = trim($uri);
    if ($uri === '') {
      return '-';
    }
    if (str_starts_with($uri, 'internal:')) {
      $rest = substr($uri, strlen('internal:'));
      $path = (string) parse_url($rest, PHP_URL_PATH);
      $query = (string) parse_url($rest, PHP_URL_QUERY);
      $fragment = (string) parse_url($rest, PHP_URL_FRAGMENT);
      $out = ($path !== '' ? $path : '/') . ($query !== '' ? '?' . $query : '') . ($fragment !== '' ? '#' . $fragment : '');
      return $out !== '' ? $out : $uri;
    }
    return $uri;
  }

  /**
   * Shortens long values for the CLI table.
   */
  private function truncateForTable(string $text, int $max = 72): string {
    if (mb_strlen($text) <= $max) {
      return $text;
    }
    return Unicode::truncate($text, $max, FALSE, TRUE);
  }

  /**
   * Parses and validates optional change-kind filter option.
   *
   * @return string[]
   *   Normalized allowed kinds, or empty array for no filter.
   */
  private function parseKindsFilter(string $kindsOption): array {
    $kindsOption = trim($kindsOption);
    if ($kindsOption === '') {
      return [];
    }
    $allowed = ['text' => TRUE, 'link' => TRUE, 'entity_reference' => TRUE];
    $matched = [];
    foreach (explode(',', $kindsOption) as $piece) {
      $kind = strtolower(trim($piece));
      if ($kind !== '' && isset($allowed[$kind])) {
        $matched[$kind] = TRUE;
      }
    }
    $filtered = array_keys($matched);
    if ($filtered === []) {
      throw new \InvalidArgumentException('Invalid --kinds value. Allowed: text,link,entity_reference');
    }
    return $filtered;
  }

  /**
   * Filters normalization changes by optional --kinds list.
   *
   * @param array<int, array<string, mixed>> $changes
   * @param string[] $kindsFilter
   *
   * @return array<int, array<string, mixed>>
   *   Changes matching the kinds filter (unchanged when the filter is empty).
   */
  private function filterChangesByKinds(array $changes, array $kindsFilter): array {
    if ($kindsFilter === []) {
      return $changes;
    }
    return array_values(array_filter($changes, static function (array $change) use ($kindsFilter): bool {
      return in_array((string) ($change['kind'] ?? ''), $kindsFilter, TRUE);
    }));
  }

  /**
   * Parses entity type option to node/paragraph list.
   *
   * @return string[]
   *   Entity type list to process.
   */
  private function parseEntityTypesOption(string $entityTypeOption): array {
    $entityTypeOption = trim(strtolower($entityTypeOption));
    if ($entityTypeOption === '' || $entityTypeOption === 'all') {
      return [...RedirectLinkQueueEnqueuer::SUPPORTED_ENTITY_TYPES];
    }
    if (!in_array($entityTypeOption, RedirectLinkQueueEnqueuer::SUPPORTED_ENTITY_TYPES, TRUE)) {
      throw new \InvalidArgumentException('Invalid --entity-type value. Allowed: node,paragraph,all');
    }
    return [$entityTypeOption];
  }

  /**
   * Saves resumable checkpoint.
   */
  private function saveProgressState(
    int $processed,
    int $updatedEntities,
    int $changedFieldValues,
    ?string $lastEntityType,
    ?int $lastEntityId,
    bool $simulate,
    bool $completed,
  ): void {
    $checkpoint = $this->state->get(self::PROGRESS_STATE_KEY, []);
    if (!is_array($checkpoint)) {
      $checkpoint = [];
    }
    $lastIds = isset($checkpoint['last_ids']) && is_array($checkpoint['last_ids']) ? $checkpoint['last_ids'] : [];
    if ($lastEntityType !== NULL && $lastEntityId !== NULL) {
      $lastIds[$lastEntityType] = $lastEntityId;
    }

    $this->state->set(self::PROGRESS_STATE_KEY, [
      'updated_at' => time(),
      'processed' => $processed,
      'updated_entities' => $updatedEntities,
      'changed_field_values' => $changedFieldValues,
      'last_entity_type' => $lastEntityType ?? ($checkpoint['last_entity_type'] ?? NULL),
      'last_entity_id' => $lastEntityId ?? ($checkpoint['last_entity_id'] ?? NULL),
      'last_ids' => $lastIds,
      'mode' => $simulate ? 'simulate' : 'execute',
      'completed' => $completed,
    ]);
  }

  /**
   * Restricts bulk node ID queries to published nodes (same as worker eligibility).
   *
   * Skipped when using --entity-ids so explicit ID lists are honored.
   */
  private function applyBulkPublishedNodeFilter(mixed $query, string $entityType, string $entityIdsOption): void {
    if ($entityType !== 'node' || $entityIdsOption !== '') {
      return;
    }
    $query->condition('status', 1);
  }

  /**
   * Filters an explicit ID list to entities with the given bundle (one query).
   *
   * @param string $entityType
   *   Either node or paragraph.
   * @param int[] $ids
   *   Entity IDs in caller order.
   * @param string $bundle
   *   Machine name of the node type or paragraph type.
   *
   * @return int[]
   *   IDs whose bundle matches, preserving caller order.
   */
  private function filterIdsByBundle(string $entityType, array $ids, string $bundle): array {
    if ($ids === [] || $bundle === '') {
      return $ids;
    }

    $idField = $entityType === 'node' ? 'nid' : 'id';
    $found = $this->entityTypeManager->getStorage($entityType)->getQuery()
      ->accessCheck(FALSE)
      ->condition($idField, $ids, 'IN')
      ->condition('type', $bundle)
      ->execute();
    if (!is_array($found) || $found === []) {
      return [];
    }

    // Fast membership test: EntityQuery::execute() returns IDs keyed by ID.
    $idsMatchingBundle = [];
    foreach ($found as $entityId) {
      $idsMatchingBundle[(int) $entityId] = TRUE;
    }

    $out = [];
    foreach ($ids as $id) {
      $id = (int) $id;
      if (isset($idsMatchingBundle[$id])) {
        $out[] = $id;
      }
    }

    return $out;
  }

  /**
   * Parses --entity-ids (comma-separated) and drops IDs below the effective floor.
   *
   * @param string $csvList
   *   Raw option value (comma-separated integers).
   * @param int $effectiveStartId
   *   Minimum ID to keep (0 keeps all positive IDs).
   *
   * @return int[]
   *   Positive IDs in list order.
   */
  private function parseCommaSeparatedEntityIds(string $csvList, int $effectiveStartId): array {
    if ($csvList === '') {
      return [];
    }
    $out = [];
    foreach (explode(',', $csvList) as $piece) {
      $id = (int) trim($piece);
      if ($id <= 0) {
        continue;
      }
      if ($effectiveStartId !== 0 && $id < $effectiveStartId) {
        continue;
      }
      $out[] = $id;
    }
    return $out;
  }

  /**
   * Start ID for this entity type after applying resume checkpoint.
   */
  private function effectiveStartIdForEntityType(
    string $entityType,
    int $startId,
    bool $resume,
    array $lastIds,
  ): int {
    $effectiveStartId = $startId;
    if (
      $resume &&
      isset($lastIds[$entityType]) &&
      is_numeric($lastIds[$entityType])
    ) {
      $effectiveStartId = max($effectiveStartId, ((int) $lastIds[$entityType]) + 1);
    }
    return $effectiveStartId;
  }

  /**
   * Base entity ID query for bulk sweep (caller adds range or count).
   */
  private function buildSweepEntityQuery(
    string $entityType,
    array $options,
    string $entityIdsOption,
    int $effectiveStartId,
  ): QueryInterface {
    $idField = $entityType === 'node' ? 'nid' : 'id';
    $query = $this->entityTypeManager->getStorage($entityType)->getQuery()
      ->accessCheck(FALSE)
      ->sort($idField);
    if ($effectiveStartId > 0) {
      $query->condition($idField, $effectiveStartId, '>=');
    }
    if (!empty($options['bundle'])) {
      $query->condition('type', $options['bundle']);
    }
    $this->applyBulkPublishedNodeFilter($query, $entityType, $entityIdsOption);
    return $query;
  }

  /**
   * Estimates how many entities will be scanned in this invocation for progress %.
   *
   * When --limit is set, the cap is the total. Otherwise counts matching IDs per
   * entity type (respecting resume start IDs and filters).
   */
  private function estimateSweepTotal(
    array $entityTypes,
    array $options,
    string $entityIdsOption,
    int $startId,
    bool $resume,
    array $lastIds,
    int $limit,
  ): int {
    if ($limit > 0) {
      return $limit;
    }
    $total = 0;
    foreach ($entityTypes as $entityType) {
      $effectiveStartId = $this->effectiveStartIdForEntityType($entityType, $startId, $resume, $lastIds);
      if ($entityIdsOption !== '') {
        $total += count($this->parseCommaSeparatedEntityIds($entityIdsOption, $effectiveStartId));
      }
      else {
        $query = $this->buildSweepEntityQuery($entityType, $options, $entityIdsOption, $effectiveStartId);
        $total += (int) $query->count()->execute();
      }
    }
    return $total;
  }

  /**
   * Builds a periodic progress notice with optional fraction and percentage.
   */
  private function buildSweepProgressNotice(
    string $mode,
    int $runProcessed,
    int $sweepTotal,
    int $processedCumulative,
    array $tailPlaceholders,
  ): string {
    $pct = ($sweepTotal > 0) ? (int) min(100, floor(100 * $runProcessed / $sweepTotal)) : NULL;
    if ($mode === 'Simulation') {
      if ($sweepTotal > 0) {
        return (string) dt(
          'Progress: processed @run of @total (@pct%) in this run; updated entities @updated; changed field values @diffs. Last @type:@id',
          array_merge([
            '@run' => $runProcessed,
            '@total' => $sweepTotal,
            '@pct' => $pct,
          ], $tailPlaceholders)
        );
      }
      return (string) dt(
        'Progress: processed @count entities; updated entities @updated; changed field values @diffs. Last @type:@id',
        array_merge([
          '@count' => $processedCumulative,
        ], $tailPlaceholders)
      );
    }
    if ($sweepTotal > 0) {
      return (string) dt(
        'Progress: scanned @run of @total (@pct%) in this run; enqueued @enqueued; already queued @already; skipped @skipped. Last @type:@id',
        array_merge([
          '@run' => $runProcessed,
          '@total' => $sweepTotal,
          '@pct' => $pct,
        ], $tailPlaceholders)
      );
    }
    return (string) dt(
      'Progress: scanned @count entities; enqueued @enqueued; already queued @already; skipped @skipped. Last @type:@id',
      array_merge([
        '@count' => $processedCumulative,
      ], $tailPlaceholders)
    );
  }

  /**
   * Whether saved state contains a real checkpoint from a prior run.
   *
   * Execute mode always applies resume logic when present, but we only notify
   * the operator when last IDs or processed counts indicate an interrupted sweep.
   */
  private function savedCheckpointHasData(mixed $saved, array $lastIds): bool {
    if (!is_array($saved) || $saved === []) {
      return FALSE;
    }
    // Finished sweep: no "resume interrupted job" wording on the next command.
    if (!empty($saved['completed'])) {
      return FALSE;
    }
    foreach ($lastIds as $value) {
      if (is_numeric($value) && (int) $value > 0) {
        return TRUE;
      }
    }
    return ((int) ($saved['processed'] ?? 0)) > 0;
  }

  /**
   * Deletes the enqueue sweep lock row from {semaphore} (see --release-enqueue-lock).
   *
   * Drupal's lock release only removes rows for the current request's lock ID.
   * Crashed processes leave a row that must be cleared out-of-band.
   */
  private function breakStaleEnqueueLock(): void {
    $this->database->delete('semaphore')
      ->condition('name', self::ENQUEUE_LOCK_NAME)
      ->execute();
  }

  /**
   * Clears stored checkpoint.
   */
  private function clearProgressState(): void {
    $this->state->delete(self::PROGRESS_STATE_KEY);
  }

  /**
   * Logs stored progress details.
   */
  private function logSavedProgress(): void {
    $checkpoint = $this->state->get(self::PROGRESS_STATE_KEY, []);
    if (!is_array($checkpoint) || $checkpoint === []) {
      if ($this->logger()) {
        $this->logger()->notice((string) dt('No saved progress checkpoint found.'));
      }
      return;
    }
    if ($this->logger()) {
      $this->logger()->notice((string) dt(
        'Saved progress: processed @processed, updated entities @updated, changed field values @values, last @type:@id, mode @mode, completed @completed, updated_at @time.',
        [
          '@processed' => (int) ($checkpoint['processed'] ?? 0),
          '@updated' => (int) ($checkpoint['updated_entities'] ?? 0),
          '@values' => (int) ($checkpoint['changed_field_values'] ?? 0),
          '@type' => (string) ($checkpoint['last_entity_type'] ?? '-'),
          '@id' => (string) ($checkpoint['last_entity_id'] ?? '-'),
          '@mode' => (string) ($checkpoint['mode'] ?? '-'),
          '@completed' => !empty($checkpoint['completed']) ? 'yes' : 'no',
          '@time' => isset($checkpoint['updated_at']) ? date('c', (int) $checkpoint['updated_at']) : '-',
        ]
      ));
    }
  }

}
