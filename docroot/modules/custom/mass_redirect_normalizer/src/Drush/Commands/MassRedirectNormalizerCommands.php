<?php

namespace Drupal\mass_redirect_normalizer\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager;
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
   * @option limit Max entities per entity type (0 = no limit).
   * @option entity-type Entity type: node, paragraph, or all (default).
   * @option bundle Limit to this bundle / paragraph type.
   * @option entity-ids Comma-separated IDs to process only (requires
   *   --entity-type=node or paragraph, not all). Ignores --limit.
   * @option simulate Dry-run: show diffs only; do not save (same as global `drush --simulate`).
   * @usage mass-redirect-normalizer:normalize-links --simulate --limit=100
   *   Preview changes. Use --format=json for machine-readable output.
   */
  public function normalizeRedirectLinks(
    $options = [
      'limit' => 0,
      'entity-type' => 'all',
      'bundle' => NULL,
      'entity-ids' => NULL,
      'simulate' => FALSE,
    ],
  ): RowsOfFields {
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
    $entityTypes = $options['entity-type'] === 'all' ? ['node', 'paragraph'] : [(string) $options['entity-type']];
    $limit = max(0, (int) $options['limit']);
    $entityIdsOption = isset($options['entity-ids']) ? trim((string) $options['entity-ids']) : '';
    try {
      $simulate = !empty($options['simulate']) || Drush::simulate();
    }
    catch (\RuntimeException) {
      // Allow PHPUnit to call this command without full Drush bootstrap.
      $simulate = !empty($options['simulate']);
    }
    $rows = [];
    $processed = 0;
    $entitiesChanged = 0;
    $fieldChanges = 0;

    if ($entityIdsOption !== '' && $options['entity-type'] === 'all') {
      throw new \InvalidArgumentException('The --entity-ids option requires --entity-type=node or --entity-type=paragraph.');
    }

    foreach ($entityTypes as $entityType) {
      if (!in_array($entityType, ['node', 'paragraph'], TRUE)) {
        $rows[] = [
          'status' => 'unsupported',
          'entity_type' => $entityType,
          'entity_id' => 'N/A',
          'parent_node_id' => '-',
          'bundle' => 'N/A',
          'field' => '-',
          'delta' => '-',
          'kind' => '-',
          'before' => '-',
          'after' => '-',
          'details' => 'Unsupported entity type',
        ];
        continue;
      }

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
        $entity = $this->entityTypeManager->getStorage($entityType)->load($id);
        if (!$entity) {
          continue;
        }

        if (!empty($options['bundle']) && $entity->bundle() !== $options['bundle']) {
          continue;
        }

        // Skip orphan paragraphs.
        if (Helper::isParagraphOrphan($entity)) {
          continue;
        }

        $result = $this->normalizerManager->normalizeEntity($entity, !$simulate, $simulate);
        $processed++;
        if (!empty($result['changed'])) {
          $entitiesChanged++;
          $changes = $result['changes'] ?? [];
          $fieldChanges += count($changes);
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
            );
            $rows[] = [
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
          }
        }
      }
    }

    $mode = $simulate ? 'SIMULATION' : 'EXECUTION';
    if ($this->logger()) {
      $this->logger()->notice((string) dt('@mode: scanned @count entities; updated: @updated; field changes: @diffs.', [
        '@mode' => $mode,
        '@count' => $processed,
        '@updated' => $entitiesChanged,
        '@diffs' => $fieldChanges,
      ]));
    }

    return new RowsOfFields($rows);
  }

  /**
   * Builds URL-only before/after values for table output.
   *
   * Link fields show a readable URI/path.
   * Text fields show changed href values in document order.
   */
  private function buildUrlBeforeAfter(string $kind, string $before, string $after): array {
    $max = 120;
    if ($kind === 'link') {
      return [
        $this->truncateForTable($this->formatUriForDisplay($before), $max),
        $this->truncateForTable($this->formatUriForDisplay($after), $max),
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
        return [
          $this->truncateForTable($beforeHrefs[0] ?? '-', $max),
          $this->truncateForTable($afterHrefs[0] ?? '-', $max),
        ];
      }
      return ['-', '-'];
    }

    $beforeUrls = implode('; ', array_column($pairs, 0));
    $afterUrls = implode('; ', array_column($pairs, 1));
    return [
      $this->truncateForTable($beforeUrls, $max),
      $this->truncateForTable($afterUrls, $max),
    ];
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
