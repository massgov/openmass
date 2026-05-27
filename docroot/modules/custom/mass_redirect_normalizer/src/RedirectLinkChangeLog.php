<?php

namespace Drupal\mass_redirect_normalizer;

use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Stores and exports redirect normalization change records.
 */
final class RedirectLinkChangeLog {

  use StringTranslationTrait;

  public const TABLE = 'mass_redirect_normalizer_change_log';

  public function __construct(
    private readonly Connection $database,
    private readonly FileSystemInterface $fileSystem,
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
        ])
        ->execute();
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

  /**
   * Exports all records to CSV in public files and returns URI.
   */
  public function exportCsv(): string {
    if (!$this->database->schema()->tableExists(self::TABLE)) {
      throw new \RuntimeException('Change log table does not exist yet.');
    }
    $directory = 'public://mnrl-reports';
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $uri = $directory . '/mnrl-change-log-' . date('Ymd-His') . '.csv';
    $path = $this->fileSystem->realpath($uri);
    if ($path === FALSE) {
      throw new \RuntimeException('Failed to resolve CSV export path.');
    }

    $handle = fopen($path, 'wb');
    if ($handle === FALSE) {
      throw new \RuntimeException('Failed to open CSV export file.');
    }

    fputcsv($handle, [
      'changed_at',
      'source',
      'entity_type',
      'entity_id',
      'bundle',
      'field_name',
      'delta',
      'kind',
      'before_value',
      'after_value',
    ]);

    $query = $this->database->select(self::TABLE, 'l')
      ->fields('l', [
        'changed_at',
        'source',
        'entity_type',
        'entity_id',
        'bundle',
        'field_name',
        'delta',
        'kind',
        'before_value',
        'after_value',
      ])
      ->orderBy('id', 'ASC');

    foreach ($query->execute() as $row) {
      fputcsv($handle, [
        date('c', (int) $row->changed_at),
        $row->source,
        $row->entity_type,
        $row->entity_id,
        $row->bundle,
        $row->field_name,
        $row->delta,
        $row->kind,
        $row->before_value,
        $row->after_value,
      ]);
    }

    fclose($handle);
    return $uri;
  }

}
