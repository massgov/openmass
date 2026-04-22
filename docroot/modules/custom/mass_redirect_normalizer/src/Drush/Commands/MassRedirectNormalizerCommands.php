<?php

namespace Drupal\mass_redirect_normalizer\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager;
use Drupal\mayflower\Helper;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;

/**
 * Drush command for redirect link normalization.
 */
final class MassRedirectNormalizerCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RedirectLinkNormalizationManager $normalizerManager,
  ) {
    parent::__construct();
  }

  /**
   * Normalizes redirect-based links in nodes and paragraphs.
   *
   * Use --simulate (or global `drush --simulate`) to preview changes only.
   * Without simulation, changes are saved.
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
    ],
  ): RowsOfFields {
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
    $entityTypes = ['node', 'paragraph'];
    $limit = max(0, (int) ($options['limit'] ?? 0));
    $entityIdsOption = isset($options['entity-ids']) ? trim((string) $options['entity-ids']) : '';
    try {
      $simulate = !empty($options['simulate']) || Drush::simulate();
    }
    catch (\RuntimeException) {
      // Allow PHPUnit to call this command without full Drush bootstrap.
      $simulate = !empty($options['simulate']);
    }
    $rows = [];
    $csvRows = [];
    $processed = 0;
    $entitiesChanged = 0;
    $valueUpdates = 0;
    $progressEvery = 100;
    $nodePublishedCache = [];
    $newerDraftCache = [];

    foreach ($entityTypes as $entityType) {
      if ($entityIdsOption !== '') {
        $ids = array_values(array_filter(array_map('intval', preg_split('/\s*,\s*/', $entityIdsOption))));
      }
      else {
        $idField = $entityType === 'node' ? 'nid' : 'id';
        $query = $this->entityTypeManager->getStorage($entityType)->getQuery()
          ->accessCheck(FALSE)
          ->sort($idField);
        if (!empty($options['bundle'])) {
          $query->condition('type', $options['bundle']);
        }
        if ($limit > 0) {
          $query->range(0, $limit);
        }
        $ids = $query->execute();
      }

      foreach ($ids as $id) {
        if ($limit > 0 && $processed >= $limit) {
          break 2;
        }

        $entity = $this->entityTypeManager->getStorage($entityType)->load($id);
        if (!$entity) {
          continue;
        }

        if (!empty($options['bundle']) && $entity->bundle() !== $options['bundle']) {
          continue;
        }

        // Skip orphan paragraphs.
        if ($entityType === 'paragraph' && Helper::isParagraphOrphan($entity)) {
          continue;
        }

        if (!$this->isEntityEligibleForNormalization(
          $entityType,
          $entity,
          $nodePublishedCache,
          $newerDraftCache,
        )) {
          continue;
        }

        $result = $this->normalizerManager->normalizeEntity($entity, !$simulate, $simulate);
        $processed++;
        if ($this->logger() && $processed % $progressEvery === 0) {
          $this->logger()->notice((string) dt('Progress: processed @count entities; updated @updated; value updates @diffs. Last @type:@id', [
            '@count' => $processed,
            '@updated' => $entitiesChanged,
            '@diffs' => $valueUpdates,
            '@type' => $entityType,
            '@id' => $id,
          ]));
        }
        if (!empty($result['changed'])) {
          $entitiesChanged++;
          $changes = $result['changes'] ?? [];
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
              'status' => $simulate ? 'would_update' : 'updated',
              'entity_type' => $entityType,
              'entity_id' => $id,
              'parent_node_id' => $parentNodeId,
              'bundle' => $entity->bundle(),
              'field' => $change['field'],
              'delta' => (string) $change['delta'],
              'kind' => $change['kind'],
              'before' => $beforePreview,
              'after' => $afterPreview,
              'details' => $simulate ? 'dry-run' : 'saved',
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
      }
    }

    $mode = $simulate ? 'SIMULATION' : 'EXECUTION';
    if ($this->logger()) {
      $limitText = $limit > 0 ? (string) $limit : 'none';
      $this->logger()->notice((string) dt('@mode: processed @count entities (limit: @limit); updated entities: @updated; value updates: @diffs.', [
        '@mode' => $mode,
        '@count' => $processed,
        '@limit' => $limitText,
        '@updated' => $entitiesChanged,
        '@diffs' => $valueUpdates,
      ]));
    }

    $csvPath = isset($options['csv-path']) ? trim((string) $options['csv-path']) : '';
    if ($csvPath !== '') {
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
   * Checks if this entity should be processed by bulk normalization.
   *
   * Bulk command targets published content only and skips nodes/paragraphs when
   * the parent node has a newer unpublished draft revision.
   */
  private function isEntityEligibleForNormalization(
    string $entityType,
    object $entity,
    array &$nodePublishedCache,
    array &$newerDraftCache,
  ): bool {
    if ($entityType === 'node') {
      if (!$entity instanceof NodeInterface) {
        return FALSE;
      }
      $nodeId = (int) $entity->id();
      $isPublished = $nodePublishedCache[$nodeId] ?? $entity->isPublished();
      $nodePublishedCache[$nodeId] = $isPublished;
      if (!$isPublished) {
        return FALSE;
      }
      return !$this->hasNewerUnpublishedDraft($entity, $newerDraftCache);
    }

    if ($entityType === 'paragraph') {
      if (!$entity instanceof Paragraph) {
        return FALSE;
      }
      $parentNode = Helper::getParentNode($entity);
      if (!$parentNode instanceof NodeInterface) {
        return FALSE;
      }
      $parentNodeId = (int) $parentNode->id();
      $parentPublished = $nodePublishedCache[$parentNodeId] ?? $parentNode->isPublished();
      $nodePublishedCache[$parentNodeId] = $parentPublished;
      if (!$parentPublished) {
        return FALSE;
      }
      return !$this->hasNewerUnpublishedDraft($parentNode, $newerDraftCache);
    }

    return FALSE;
  }

  /**
   * Returns TRUE when latest node revision is unpublished and newer.
   */
  private function hasNewerUnpublishedDraft(NodeInterface $node, array &$cache): bool {
    $nodeId = (int) $node->id();
    if (array_key_exists($nodeId, $cache)) {
      return $cache[$nodeId];
    }

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('node');
    $latestRevisionId = $storage->getLatestRevisionId($node->id());
    if (!$latestRevisionId || (int) $latestRevisionId === (int) $node->getRevisionId()) {
      $cache[$nodeId] = FALSE;
      return $cache[$nodeId];
    }

    $revisions = $storage->loadMultipleRevisions([(int) $latestRevisionId]);
    $latest = $revisions[(int) $latestRevisionId] ?? NULL;
    if (!$latest instanceof NodeInterface) {
      $cache[$nodeId] = FALSE;
      return $cache[$nodeId];
    }

    $cache[$nodeId] = !$latest->isPublished();
    return $cache[$nodeId];
  }

  /**
   * Builds URL-only before/after values for table output.
   *
   * Link fields show a readable URI/path.
   * Text fields show changed href values in document order.
   */
  private function buildUrlBeforeAfter(string $kind, string $before, string $after, bool $truncate = TRUE): array {
    $max = 120;
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

}
