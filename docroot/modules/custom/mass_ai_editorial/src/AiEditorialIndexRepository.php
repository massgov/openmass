<?php

namespace Drupal\mass_ai_editorial;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Merge;

/**
 * Persists the local AI editorial indexing outbox and chunk records.
 */
class AiEditorialIndexRepository {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Queues or refreshes a document row without rendering content yet.
   */
  public function queueDocument(array $values): int {
    $now = $this->time->getRequestTime();
    $values += [
      'view_mode' => 'ai_index',
      'status' => 'queued',
      'queued_at' => $now,
      'changed_at' => $now,
    ];

    $merge = $this->database->merge('mass_ai_editorial_document')
      ->keys([
        'entity_type' => $values['entity_type'],
        'entity_id' => $values['entity_id'],
        'langcode' => $values['langcode'],
        'view_mode' => $values['view_mode'],
      ])
      ->fields($values);
    $this->executeMerge($merge);

    return $this->loadDocumentId($values['entity_type'], (int) $values['entity_id'], $values['langcode'], $values['view_mode']);
  }

  /**
   * Stores rendered text and replaces chunks when the content hash changed.
   */
  public function storeRenderedDocument(int $document_id, string $text, array $chunks, string $status = 'ready_for_embeddings'): void {
    $now = $this->time->getRequestTime();
    $hash = hash('sha256', $text);

    $previous_hash = $this->database->select('mass_ai_editorial_document', 'd')
      ->fields('d', ['content_hash'])
      ->condition('id', $document_id)
      ->execute()
      ->fetchField();

    $fields = [
      'content_hash' => $hash,
      'rendered_text' => $text,
      'status' => $previous_hash === $hash ? 'unchanged' : $status,
      'indexed_at' => $now,
      'error' => NULL,
    ];
    $this->database->update('mass_ai_editorial_document')
      ->fields($fields)
      ->condition('id', $document_id)
      ->execute();

    if ($previous_hash === $hash) {
      return;
    }

    $this->database->delete('mass_ai_editorial_chunk')
      ->condition('document_id', $document_id)
      ->execute();

    foreach ($chunks as $chunk) {
      $this->database->insert('mass_ai_editorial_chunk')
        ->fields([
          'document_id' => $document_id,
          'chunk_delta' => $chunk['delta'],
          'chunk_hash' => $chunk['hash'],
          'heading' => $chunk['heading'],
          'text' => $chunk['text'],
          'token_estimate' => $chunk['token_estimate'],
          'created_at' => $now,
        ])
        ->execute();
    }
  }

  /**
   * Marks a document as failed while preserving its queue record.
   */
  public function markFailed(int $document_id, string $message): void {
    $this->database->update('mass_ai_editorial_document')
      ->fields([
        'status' => 'failed',
        'error' => mb_substr($message, 0, 4096),
      ])
      ->condition('id', $document_id)
      ->execute();
  }

  /**
   * Marks all rows for an entity as deleted.
   */
  public function markDeleted(string $entity_type, int $entity_id): void {
    $this->database->update('mass_ai_editorial_document')
      ->fields([
        'status' => 'deleted',
        'changed_at' => $this->time->getRequestTime(),
      ])
      ->condition('entity_type', $entity_type)
      ->condition('entity_id', $entity_id)
      ->execute();
  }

  /**
   * Returns queued document rows for processing.
   */
  public function loadQueuedDocuments(int $limit, bool $include_failed = TRUE): array {
    $statuses = $include_failed ? ['queued', 'failed'] : ['queued'];

    return $this->database->select('mass_ai_editorial_document', 'd')
      ->fields('d')
      ->condition('status', $statuses, 'IN')
      ->orderBy('queued_at')
      ->range(0, $limit)
      ->execute()
      ->fetchAllAssoc('id');
  }

  /**
   * Returns chunks that have not yet been embedded with the requested model.
   */
  public function loadChunksForEmbedding(string $model, int $limit): array {
    $query = $this->database->select('mass_ai_editorial_chunk', 'c');
    $query->join('mass_ai_editorial_document', 'd', 'd.id = c.document_id');
    $query->addField('c', 'id', 'chunk_id');
    $query->addField('c', 'document_id', 'local_document_id');
    $query->addField('c', 'chunk_delta', 'chunk_delta');
    $query->addField('c', 'chunk_hash', 'chunk_hash');
    $query->addField('c', 'heading', 'heading');
    $query->addField('c', 'text', 'chunk_text');
    $query->addField('c', 'token_estimate', 'token_estimate');
    $query->addField('d', 'entity_type', 'entity_type');
    $query->addField('d', 'entity_id', 'entity_id');
    $query->addField('d', 'revision_id', 'revision_id');
    $query->addField('d', 'langcode', 'langcode');
    $query->addField('d', 'org_id', 'org_id');
    $query->addField('d', 'bundle', 'bundle');
    $query->addField('d', 'title', 'title');
    $query->addField('d', 'url', 'url');
    $query->addField('d', 'content_hash', 'content_hash');
    $query->condition('d.status', ['ready_for_embeddings', 'unchanged'], 'IN');
    $model_group = $query->orConditionGroup()
      ->isNull('c.embedding_model')
      ->condition('c.embedding_model', $model, '<>');
    $query->condition($model_group);
    $query->orderBy('d.entity_id');
    $query->orderBy('c.chunk_delta');
    $query->range(0, $limit);

    return $query->execute()->fetchAll();
  }

  /**
   * Returns current chunk positions for a rendered document.
   *
   * @return array<int>
   *   Chunk deltas currently stored for the local document.
   */
  public function loadChunkDeltas(int $document_id): array {
    return array_map('intval', $this->database->select('mass_ai_editorial_chunk', 'c')
      ->fields('c', ['chunk_delta'])
      ->condition('document_id', $document_id)
      ->orderBy('chunk_delta')
      ->execute()
      ->fetchCol());
  }

  /**
   * Marks a local chunk as embedded in the external vector store.
   */
  public function markChunkEmbedded(int $chunk_id, string $model, string $embedding_ref): void {
    $this->database->update('mass_ai_editorial_chunk')
      ->fields([
        'embedding_model' => $model,
        'embedding_ref' => $embedding_ref,
      ])
      ->condition('id', $chunk_id)
      ->execute();
  }

  /**
   * Clears all POC rows.
   */
  public function reset(): void {
    $this->database->truncate('mass_ai_editorial_chunk')->execute();
    $this->database->truncate('mass_ai_editorial_document')->execute();
  }

  /**
   * Returns current document/chunk counts grouped by status.
   */
  public function stats(): array {
    $statuses = $this->database->select('mass_ai_editorial_document', 'd')
      ->fields('d', ['status'])
      ->groupBy('status');
    $statuses->addExpression('COUNT(*)', 'count');

    return [
      'documents' => $statuses->execute()->fetchAllKeyed(),
      'chunks' => (int) $this->database->select('mass_ai_editorial_chunk', 'c')
        ->countQuery()
        ->execute()
        ->fetchField(),
      'embedded_chunks' => (int) $this->database->select('mass_ai_editorial_chunk', 'c')
        ->isNotNull('embedding_model')
        ->countQuery()
        ->execute()
        ->fetchField(),
    ];
  }

  private function loadDocumentId(string $entity_type, int $entity_id, string $langcode, string $view_mode): int {
    return (int) $this->database->select('mass_ai_editorial_document', 'd')
      ->fields('d', ['id'])
      ->condition('entity_type', $entity_type)
      ->condition('entity_id', $entity_id)
      ->condition('langcode', $langcode)
      ->condition('view_mode', $view_mode)
      ->execute()
      ->fetchField();
  }

  private function executeMerge(Merge $merge): void {
    $merge->execute();
  }

}
