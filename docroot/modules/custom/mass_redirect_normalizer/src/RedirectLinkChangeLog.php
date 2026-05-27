<?php

namespace Drupal\mass_redirect_normalizer;

use Drupal\Core\Database\Connection;

/**
 * Stores redirect normalization change records for the report view.
 */
final class RedirectLinkChangeLog {

  public const TABLE = 'mass_redirect_normalizer_change_log';

  public const STATUS_SUCCEEDED = 'succeeded';

  public const STATUS_FAILED = 'failed';

  public function __construct(
    private readonly Connection $database,
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
      $this->database->insert(self::TABLE)
        ->fields([
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
        ])
        ->execute();
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
    $this->database->insert(self::TABLE)
      ->fields([
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
      ])
      ->execute();
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
