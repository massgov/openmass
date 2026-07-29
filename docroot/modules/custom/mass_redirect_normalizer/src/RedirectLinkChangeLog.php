<?php

namespace Drupal\mass_redirect_normalizer;

use Drupal\Core\Database\Connection;
use Psr\Log\LoggerInterface;

/**
 * Stores redirect normalization change records for the report view.
 */
final class RedirectLinkChangeLog {

  public const TABLE = 'mass_redirect_normalizer_change_log';

  public const STATUS_SUCCEEDED = 'succeeded';

  public const STATUS_FAILED = 'failed';

  public const STATUS_SKIPPED = 'skipped';

  public function __construct(
    private readonly Connection $database,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Logs field-level changes for one entity.
   *
   * @param array<int, array<string, mixed>> $changes
   *   Normalization changes from RedirectLinkNormalizationManager::normalizeEntity().
   */
  public function logChanges(
    string $entityType,
    int $entityId,
    string $bundle,
    string $source,
    array $changes,
  ): void {
    if (!$this->database->schema()->tableExists(self::TABLE)) {
      return;
    }
    $now = time();
    foreach ($changes as $change) {
      $this->insertRow([
        'changed_at' => $now,
        'source' => $source,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'bundle' => $bundle,
        'field_name' => (string) ($change['field'] ?? ''),
        'delta' => (int) ($change['delta'] ?? 0),
        'kind' => (string) ($change['kind'] ?? ''),
        'before_value' => (string) ($change['before'] ?? ''),
        'after_value' => (string) ($change['after'] ?? ''),
        'status' => self::STATUS_SUCCEEDED,
        'error_message' => NULL,
      ], $entityType, $entityId);
    }
  }

  /**
   * Logs rewrites the normalizer refused to make for one entity.
   *
   * Each skip records the link value, the target the rewrite would have
   * produced, and the reason (e.g. unpublished_target, live_source_page),
   * so the content team can find and clean up stale redirects.
   */
  public function logSkips(
    string $entityType,
    int $entityId,
    string $bundle,
    string $source,
    array $skips,
  ): void {
    if (!$this->database->schema()->tableExists(self::TABLE)) {
      return;
    }
    $now = time();
    foreach ($skips as $skip) {
      $this->insertRow([
        'changed_at' => $now,
        'source' => $source,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'bundle' => $bundle,
        'field_name' => (string) ($skip['field'] ?? ''),
        'delta' => (int) ($skip['delta'] ?? 0),
        'kind' => (string) ($skip['kind'] ?? ''),
        'before_value' => (string) ($skip['before'] ?? ''),
        'after_value' => (string) ($skip['after'] ?? ''),
        'status' => self::STATUS_SKIPPED,
        'error_message' => (string) ($skip['reason'] ?? ''),
      ], $entityType, $entityId);
    }
  }

  /**
   * Logs a normalization failure for one entity.
   */
  public function logFailure(
    string $entityType,
    int $entityId,
    string $bundle,
    string $source,
    string $message,
  ): void {
    if (!$this->database->schema()->tableExists(self::TABLE)) {
      return;
    }
    $this->insertRow([
      'changed_at' => time(),
      'source' => $source,
      'entity_type' => $entityType,
      'entity_id' => $entityId,
      'bundle' => $bundle,
      'field_name' => '',
      'delta' => 0,
      'kind' => '',
      'before_value' => NULL,
      'after_value' => NULL,
      'status' => self::STATUS_FAILED,
      'error_message' => $message,
    ], $entityType, $entityId);
  }

  /**
   * Inserts one change-log row without letting DB errors escape.
   */
  private function insertRow(array $fields, string $entityType, int $entityId): void {
    try {
      $this->database->insert(self::TABLE)
        ->fields($fields)
        ->execute();
    }
    catch (\Throwable $exception) {
      $this->logger->error('Redirect link normalization change log insert failed for @type:@id: @message', [
        '@type' => $entityType,
        '@id' => $entityId,
        '@message' => $exception->getMessage(),
      ]);
    }
  }

  /**
   * Removes all change log rows.
   */
  public function clearAll(): void {
    if (!$this->database->schema()->tableExists(self::TABLE)) {
      return;
    }
    $this->database->truncate(self::TABLE)->execute();
  }

}
