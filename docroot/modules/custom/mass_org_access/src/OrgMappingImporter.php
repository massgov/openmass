<?php

declare(strict_types=1);

namespace Drupal\mass_org_access;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Parses and applies org_page → Permission Group mappings from a CSV.
 *
 * Lets the content team replace the manual hierarchy-picker entry with a
 * "nodeid,termid" CSV: each org_page's field_content_organization is set to
 * the union of its mapped terms plus ancestors (the same set the picker would
 * produce). Used by OrgMappingImportForm's batch.
 */
class OrgMappingImporter {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Max example bad rows kept so a wrong file cannot flood the messenger.
   */
  private const MAX_SAMPLES = 10;

  /**
   * Result statuses returned by apply().
   */
  public const IMPORTED = 'imported';
  public const SKIPPED = 'skipped';

  /**
   * Parses a "nodeid,termid" CSV, grouped by org_page node.
   *
   * Requires a header row whose first two columns are exactly "nodeid" and
   * "termid" — that is the column-presence check that rejects unrelated files.
   * Below the header, blank lines are ignored and any row that is not two
   * numeric columns is counted (with a few samples kept) but not imported.
   *
   * @param string $uri
   *   The uploaded file URI.
   *
   * @return array{valid_header: bool, mappings: array<int, int[]>, invalid: int, samples: string[]}
   *   valid_header: whether the required nodeid,termid header was present.
   *   mappings: org_page NID => list of user_organization term IDs.
   *   invalid: count of non-blank rows that were not "nodeid,termid".
   *   samples: up to MAX_SAMPLES example bad rows.
   */
  public function parse(string $uri): array {
    $empty = ['valid_header' => FALSE, 'mappings' => [], 'invalid' => 0, 'samples' => []];
    $handle = fopen($uri, 'r');
    if ($handle === FALSE) {
      return $empty;
    }

    $header = fgetcsv($handle);
    $columns = is_array($header)
      ? array_map(static fn($value) => strtolower(trim((string) $value)), $header)
      : [];
    if (($columns[0] ?? '') !== 'nodeid' || ($columns[1] ?? '') !== 'termid') {
      fclose($handle);
      return $empty;
    }

    $mappings = [];
    $samples = [];
    $invalid = 0;
    $line = 1;
    while (($row = fgetcsv($handle)) !== FALSE) {
      $line++;
      $nid = isset($row[0]) ? trim((string) $row[0]) : '';
      $tid = isset($row[1]) ? trim((string) $row[1]) : '';
      if (!is_numeric($nid) || !is_numeric($tid)) {
        // Ignore blank lines; count other junk but keep only a few samples.
        if (array_filter(array_map(static fn($value) => trim((string) $value), $row))) {
          $invalid++;
          if (count($samples) < self::MAX_SAMPLES) {
            $samples[] = sprintf('Row %d: "%s".', $line, implode(',', $row));
          }
        }
        continue;
      }
      $mappings[(int) $nid][(int) $tid] = (int) $tid;
    }
    fclose($handle);

    foreach ($mappings as $nid => $tids) {
      $mappings[$nid] = array_values($tids);
    }
    return ['valid_header' => TRUE, 'mappings' => $mappings, 'invalid' => $invalid, 'samples' => $samples];
  }

  /**
   * Applies one org_page's Permission Groups from a list of term IDs.
   *
   * Validates that the node is an org_page and each term is a user_organization
   * term, expands ancestors (matching the admin hierarchy picker), and writes
   * the result onto BOTH the published (default) revision and any forward draft
   * — in place, no new revision, setSyncing(TRUE) — the same way drush moab
   * keeps a pending draft's editors from being locked out.
   *
   * @param int $nid
   *   org_page node ID.
   * @param int[] $tids
   *   user_organization term IDs from the CSV for this node.
   *
   * @return array{nid: int, status: string, reason?: string, tids?: int[], invalid_tids?: int[], revisions?: string[]}
   *   Structured result describing what happened, for the import log.
   */
  public function apply(int $nid, array $tids): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $node = $storage->load($nid);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'org_page') {
      return ['nid' => $nid, 'status' => self::SKIPPED, 'reason' => 'not an organization page'];
    }
    if (!$node->hasField('field_content_organization')) {
      return ['nid' => $nid, 'status' => self::SKIPPED, 'reason' => 'no Permission Groups field'];
    }

    [$resolved, $invalid_tids] = $this->resolveTermsWithAncestors($tids);
    if (empty($resolved)) {
      return [
        'nid' => $nid,
        'status' => self::SKIPPED,
        'reason' => 'no valid user_organization terms',
        'invalid_tids' => $invalid_tids,
      ];
    }

    $revisions = $this->writeToPublishedAndDraft($node, $resolved, $storage);
    return [
      'nid' => $nid,
      'status' => self::IMPORTED,
      'tids' => $resolved,
      'invalid_tids' => $invalid_tids,
      'revisions' => $revisions,
    ];
  }

  /**
   * Resolves CSV term IDs to valid user_organization terms plus ancestors.
   *
   * @param int[] $tids
   *   Raw term IDs from the CSV.
   *
   * @return array{0: int[], 1: int[]}
   *   [resolved term IDs (terms + ancestors), invalid term IDs ignored].
   */
  private function resolveTermsWithAncestors(array $tids): array {
    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $terms = $term_storage->loadMultiple($tids);
    $resolved = [];
    $invalid = [];
    foreach ($tids as $tid) {
      $term = $terms[$tid] ?? NULL;
      if (!$term || $term->bundle() !== 'user_organization') {
        $invalid[] = $tid;
        continue;
      }
      foreach ($term_storage->loadAllParents($tid) as $ptid => $parent) {
        $resolved[(int) $ptid] = (int) $ptid;
      }
    }
    return [array_values($resolved), $invalid];
  }

  /**
   * Writes the terms onto the published revision and any forward draft.
   *
   * @return string[]
   *   The revisions written ('published', and 'draft' when one exists).
   */
  private function writeToPublishedAndDraft(NodeInterface $node, array $tids, EntityStorageInterface $storage): array {
    $written = ['published'];
    $this->writeRevision($node, $tids, $storage);

    $latest_vid = $storage->getLatestRevisionId($node->id());
    if ($latest_vid && (int) $latest_vid !== (int) $node->getRevisionId()) {
      $draft = $storage->loadRevision($latest_vid);
      if ($draft instanceof NodeInterface) {
        $this->writeRevision($draft, $tids, $storage);
        $written[] = 'draft';
      }
    }
    return $written;
  }

  /**
   * Sets field_content_organization on one revision and saves it in place.
   */
  private function writeRevision(NodeInterface $revision, array $tids, EntityStorageInterface $storage): void {
    $revision->set(
      'field_content_organization',
      array_map(fn(int $tid) => ['target_id' => $tid], $tids)
    );
    $revision->setNewRevision(FALSE);
    $revision->setSyncing(TRUE);
    $storage->save($revision);
  }

  /**
   * Starts the import log file: header plus the rows the parser ignored.
   *
   * @return string
   *   The log file URI, or '' if it could not be created.
   */
  public function startLog(string $filename, array $parsed): string {
    $uri = uniqid('temporary://org-mapping-import-', TRUE) . '.log';
    $handle = @fopen($uri, 'w');
    if ($handle === FALSE) {
      return '';
    }
    fwrite($handle, "Organization mapping import\n");
    fwrite($handle, 'Date: ' . date('Y-m-d H:i:s') . "\n");
    fwrite($handle, 'Source file: ' . $filename . "\n");
    fwrite($handle, str_repeat('=', 60) . "\n\n");

    $invalid = (int) ($parsed['invalid'] ?? 0);
    if ($invalid > 0) {
      fwrite($handle, sprintf("Rows ignored (not \"nodeid,termid\"): %d\n", $invalid));
      foreach ($parsed['samples'] ?? [] as $sample) {
        fwrite($handle, '  ' . $sample . "\n");
      }
      $shown = count($parsed['samples'] ?? []);
      if ($invalid > $shown) {
        fwrite($handle, sprintf("  …and %d more not shown.\n", $invalid - $shown));
      }
      fwrite($handle, "\n");
    }
    fwrite($handle, "Mappings:\n");
    fclose($handle);
    return $uri;
  }

  /**
   * Appends one node's apply() result to the log.
   */
  public function logResult(string $uri, array $result): void {
    if ($uri === '' || ($handle = @fopen($uri, 'a')) === FALSE) {
      return;
    }
    if (($result['status'] ?? '') === self::IMPORTED) {
      $line = sprintf(
        '  Node %d: IMPORTED terms [%s] to %s',
        $result['nid'],
        implode(', ', $result['tids'] ?? []),
        implode(' + ', $result['revisions'] ?? [])
      );
      if (!empty($result['invalid_tids'])) {
        $line .= sprintf(' (ignored invalid term IDs: %s)', implode(', ', $result['invalid_tids']));
      }
    }
    else {
      $line = sprintf('  Node %d: SKIPPED — %s', $result['nid'], $result['reason'] ?? 'unknown');
      if (!empty($result['invalid_tids'])) {
        $line .= sprintf(' (invalid term IDs: %s)', implode(', ', $result['invalid_tids']));
      }
    }
    fwrite($handle, $line . "\n");
    fclose($handle);
  }

  /**
   * Appends the summary footer to the log.
   */
  public function finishLog(string $uri, int $imported, int $skipped): void {
    if ($uri === '' || ($handle = @fopen($uri, 'a')) === FALSE) {
      return;
    }
    fwrite($handle, "\n" . str_repeat('=', 60) . "\n");
    fwrite($handle, "Summary\n");
    fwrite($handle, sprintf("  Organization pages updated: %d\n", $imported));
    fwrite($handle, sprintf("  Nodes skipped: %d\n", $skipped));
    fclose($handle);
  }

}
