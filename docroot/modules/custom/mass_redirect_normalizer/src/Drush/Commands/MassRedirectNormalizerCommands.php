<?php

namespace Drupal\mass_redirect_normalizer\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\mass_redirect_normalizer\RedirectLinkNormalizationEligibility;
use Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager;
use Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer;
use Drupal\mayflower\Helper;
use Drupal\paragraphs\Entity\Paragraph;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;

/**
 * Drush command for redirect link normalization.
 */
final class MassRedirectNormalizerCommands extends DrushCommands {

  use AutowireTrait;
  private const PROGRESS_STATE_KEY = 'mass_redirect_normalizer.command_progress';

  private const ENQUEUE_LOCK_NAME = 'mass_redirect_normalizer.enqueue';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RedirectLinkNormalizationManager $normalizerManager,
    protected StateInterface $state,
    protected RedirectLinkQueueEnqueuer $enqueuer,
    protected RedirectLinkNormalizationEligibility $eligibility,
    protected LockBackendInterface $lock,
  ) {
    parent::__construct();
  }

  /**
   * Normalizes redirect-based links in nodes and paragraphs.
   *
   * Use --simulate (or global `drush --simulate`) to preview changes only.
   * Without simulation, eligible entities are enqueued for queue workers.
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
   * @option csv-path Optional absolute path to write a CSV report file.
   * @option kinds Comma-separated change kinds to include: text,link,entity_reference.
   * @option entity-type Limit processing to node or paragraph (default: both).
   * @option start-id Start scanning at this numeric entity ID (inclusive).
   * @option resume Continue from saved checkpoint.
   * @option show-progress Print saved checkpoint and exit.
   * @option reset-progress Clear saved checkpoint before running.
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
    $progressEvery = 100;
    $nodePublishedCache = [];
    $newerDraftCache = [];
    $kindsFilter = $this->parseKindsFilter(isset($options['kinds']) ? (string) $options['kinds'] : '');
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
    if ($resume && $this->logger()) {
      $this->logger()->notice((string) dt('Continuing from saved checkpoint.'));
    }

    $lockAcquired = FALSE;
    if (!$simulate) {
      if (!$this->lock->acquire(self::ENQUEUE_LOCK_NAME, 3600)) {
        if ($this->logger()) {
          $this->logger()->warning((string) dt('Another redirect link normalization enqueue sweep is already running. Exiting.'));
        }
        return new RowsOfFields([]);
      }
      $lockAcquired = TRUE;
    }

    try {
      foreach ($entityTypes as $entityType) {
        $effectiveStartId = $startId;
        if (
        $resume &&
        isset($lastIds[$entityType]) &&
        is_numeric($lastIds[$entityType])
        ) {
          $effectiveStartId = max($effectiveStartId, ((int) $lastIds[$entityType]) + 1);
        }
        if ($entityIdsOption !== '') {
          $ids = array_values(array_filter(array_map('intval', preg_split('/\s*,\s*/', $entityIdsOption)), function (int $id) use ($effectiveStartId): bool {
            return $id > 0 && ($effectiveStartId === 0 || $id >= $effectiveStartId);
          }));
        }
        else {
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
          if ($limit > 0) {
            $query->range(0, $limit);
          }
          $ids = $query->execute();
        }

        foreach ($ids as $id) {
          if ($limit > 0 && $runProcessed >= $limit) {
            break 2;
          }

          $entity = $this->entityTypeManager->getStorage($entityType)->load($id);
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
            if ($this->logger() && $runProcessed % $progressEvery === 0) {
              $this->logger()->notice((string) dt('Progress: processed @count entities; updated entities @updated; changed field values @diffs. Last @type:@id', [
                '@count' => $processed,
                '@updated' => $entitiesChanged,
                '@diffs' => $valueUpdates,
                '@type' => $entityType,
                '@id' => $id,
              ]));
            }
            $this->saveProgressState($processed, $entitiesChanged, $valueUpdates, $entityType, (int) $id, $simulate, FALSE);
            if (!empty($result['changed'])) {
              $changes = $result['changes'] ?? [];
              if ($kindsFilter !== []) {
                $changes = array_values(array_filter($changes, function (array $change) use ($kindsFilter): bool {
                  return in_array((string) ($change['kind'] ?? ''), $kindsFilter, TRUE);
                }));
              }
              if ($changes === []) {
                continue;
              }
              $entitiesChanged++;
              $valueUpdates += count($changes);
              $parentNodeId = '-';
              if ($entityType === 'paragraph' && $entity instanceof Paragraph) {
                $parentNode = Helper::getParentNode($entity);
                $parentNodeId = $parentNode ? (string) $parentNode->id() : '-';
              }
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
                  'status' => 'would_update',
                  'entity_type' => $entityType,
                  'entity_id' => $id,
                  'parent_node_id' => $parentNodeId,
                  'bundle' => $entity->bundle(),
                  'field' => $change['field'],
                  'delta' => (string) $change['delta'],
                  'kind' => $change['kind'],
                  'before' => $beforePreview,
                  'after' => $afterPreview,
                  'details' => 'dry-run',
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
              }
            }
            continue;
          }

          $enqueueResult = $this->enqueuer->enqueueById($entityType, (int) $id, 'drush');
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

          if ($this->logger() && $runProcessed % $progressEvery === 0) {
            $this->logger()->notice((string) dt('Progress: scanned @count entities; enqueued @enqueued; already queued @already; skipped @skipped. Last @type:@id', [
              '@count' => $processed,
              '@enqueued' => $enqueued,
              '@already' => $alreadyQueued,
              '@skipped' => $skippedIneligible,
              '@type' => $entityType,
              '@id' => $id,
            ]));
          }
          $this->saveProgressState($processed, $enqueued, $valueUpdates, $entityType, (int) $id, $simulate, FALSE);
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
        $this->logger()->notice((string) dt('ENQUEUE: scanned @count entities (limit: @limit); enqueued @enqueued; already queued @already; skipped @skipped.', [
          '@count' => $processed,
          '@limit' => $limitText,
          '@enqueued' => $enqueued,
          '@already' => $alreadyQueued,
          '@skipped' => $skippedIneligible,
        ]));
      }
      $this->saveProgressState($processed, $enqueued, $valueUpdates, NULL, NULL, $simulate, TRUE);
    }

    $csvPath = isset($options['csv-path']) ? trim((string) $options['csv-path']) : '';
    if ($csvPath !== '' && $simulate) {
      $this->writeCsvReport($csvPath, $csvRows);
      if ($this->logger()) {
        $this->logger()->notice((string) dt('CSV report written to @path with @count row(s).', [
          '@path' => $csvPath,
          '@count' => count($csvRows),
        ]));
      }
    }

    return new RowsOfFields($rows);
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
   * Writes full report rows to a CSV file path.
   */
  private function writeCsvReport(string $path, array $rows): void {
    $directory = dirname($path);
    if (!is_dir($directory) && !@mkdir($directory, 0775, TRUE) && !is_dir($directory)) {
      throw new \RuntimeException(sprintf('Could not create CSV directory: %s', $directory));
    }

    $handle = @fopen($path, 'wb');
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
    fputcsv($handle, $header);
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
    $allowed = ['text', 'link', 'entity_reference'];
    $parts = array_values(array_filter(array_map(static fn(string $value): string => trim(strtolower($value)), explode(',', $kindsOption))));
    $filtered = array_values(array_unique(array_intersect($parts, $allowed)));
    if ($filtered === []) {
      throw new \InvalidArgumentException('Invalid --kinds value. Allowed: text,link,entity_reference');
    }
    return $filtered;
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
      return ['node', 'paragraph'];
    }
    if (!in_array($entityTypeOption, ['node', 'paragraph'], TRUE)) {
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
