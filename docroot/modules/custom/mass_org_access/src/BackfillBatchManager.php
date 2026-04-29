<?php

namespace Drupal\mass_org_access;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Builds and manages the field_content_organization backfill batch.
 */
class BackfillBatchManager {

  use StringTranslationTrait;

  const BATCH_SIZE = 100;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Sets up the batch to populate field_content_organization on all content.
   */
  public function queueBackfill(): void {
    batch_set($this->generateBatch());
  }

  /**
   * Builds the batch definition.
   */
  public function generateBatch(): array {
    $node_ids = array_values($this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->execute());

    $media_ids = array_values($this->entityTypeManager->getStorage('media')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('bundle', 'document')
      ->execute());

    $total = count($node_ids) + count($media_ids);
    $operations = [];

    foreach (array_chunk($node_ids, self::BATCH_SIZE) as $chunk) {
      $operations[] = [
        '\Drupal\mass_org_access\BackfillBatchManager::processChunk',
        ['node', $chunk, $total],
      ];
    }
    foreach (array_chunk($media_ids, self::BATCH_SIZE) as $chunk) {
      $operations[] = [
        '\Drupal\mass_org_access\BackfillBatchManager::processChunk',
        ['media', $chunk, $total],
      ];
    }

    return [
      'operations' => $operations,
      'finished' => '\Drupal\mass_org_access\BackfillBatchManager::batchFinished',
      'title' => $this->t('Backfilling organization access field'),
      'progress_message' => $this->t('Batch @current of @total'),
      'error_message' => $this->t('Backfill encountered an error.'),
    ];
  }

  /**
   * Batch worker: syncs field_content_organization for a chunk of entities.
   *
   * Uses setNewRevision(FALSE) to avoid creating unnecessary revisions.
   */
  public static function processChunk(string $entity_type_id, array $entity_ids, int $total, array &$context): void {
    if (empty($context['sandbox']['processed'])) {
      $context['sandbox']['processed'] = 0;
    }

    /** @var \Drupal\mass_org_access\OrgAccessChecker $checker */
    $checker = \Drupal::service('mass_org_access.org_access_checker');
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);

    foreach ($storage->loadMultiple($entity_ids) as $entity) {
      $checker->syncContentOrganization($entity);
      if (method_exists($entity, 'setNewRevision')) {
        $entity->setNewRevision(FALSE);
      }
      $entity->setSyncing(TRUE);
      $storage->save($entity);
      $context['results'][] = "$entity_type_id:{$entity->id()}";
    }

    $context['sandbox']['processed'] += count($entity_ids);
    $context['message'] = t('Processed @done of @total entities', [
      '@done' => count($context['results']),
      '@total' => $total,
    ]);
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished(bool $success, array $results, array $operations): void {
    if ($success) {
      \Drupal::messenger()->addMessage(t('Backfill complete: updated @count entities.', [
        '@count' => count($results),
      ]));
    }
    else {
      $error_operation = reset($operations);
      \Drupal::messenger()->addError(t('Backfill failed at operation: @op', [
        '@op' => $error_operation[0],
      ]));
    }
  }

}
