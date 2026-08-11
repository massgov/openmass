<?php

namespace Drupal\mass_ai_editorial\Drush\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\mass_ai_editorial\AiEditorialIndexer;
use Drupal\mass_ai_editorial\AiEditorialIndexRepository;
use Drupal\node\NodeInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use GuzzleHttp\ClientInterface;

/**
 * Drush commands for the local AI editorial prototype.
 */
class MassAiEditorialCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly StateInterface $state,
    private readonly ClientInterface $httpClient,
    private readonly AiEditorialIndexer $indexer,
    private readonly AiEditorialIndexRepository $repository,
  ) {
    parent::__construct();
  }

  /**
   * Queue a bounded organization slice for the AI editorial POC.
   *
   * @command mass-ai-editorial:queue-poc
   * @aliases maie-queue
   * @option org-id
   *   Organization node ID. Defaults to Department of Unemployment Assistance.
   * @option limit
   *   Maximum number of nodes to queue.
   * @option reset
   *   Clear prototype tables before queueing.
   * @option track
   *   Track this org for incremental entity-save queueing.
   * @usage drush mass-ai-editorial:queue-poc --org-id=5376 --limit=99 --reset
   *   Queue the DUA POC slice from the editorial admin search filter.
   */
  public function queuePoc(array $options = ['org-id' => 5376, 'limit' => 99, 'reset' => FALSE, 'track' => TRUE]): void {
    $org_id = (int) $options['org-id'];
    $limit = (int) $options['limit'];

    if ((bool) $options['reset']) {
      $this->repository->reset();
      $this->output()->writeln('<comment>Reset AI editorial prototype tables.</comment>');
    }

    if ((bool) $options['track']) {
      $tracked = $this->state->get('mass_ai_editorial.tracked_org_ids', []);
      $tracked[] = $org_id;
      $this->state->set('mass_ai_editorial.tracked_org_ids', array_values(array_unique(array_map('intval', $tracked))));
    }

    $node_ids = $this->loadPocNodeIds($org_id, $limit);
    $node_ids = array_values(array_unique($node_ids));
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($node_ids);
    $queued = 0;

    foreach ($node_ids as $node_id) {
      $node = $nodes[$node_id] ?? NULL;
      if ($node instanceof NodeInterface && $this->indexer->queueNode($node, $org_id) !== NULL) {
        $queued++;
      }
    }

    $this->output()->writeln(sprintf('<info>Queued %d node(s) for org %d.</info>', $queued, $org_id));
    $this->output()->writeln('Process with: drush mass-ai-editorial:process-queue --limit=25');
  }

  /**
   * Queue, render, chunk, and embed content for every published organization.
   *
   * @command mass-ai-editorial:index-all-orgs
   * @aliases maie-index-all-orgs
   * @option org-limit
   *   Maximum number of published organization pages to index. Use 0 for all.
   * @option content-limit
   *   Maximum number of content nodes to queue per organization. Use 0 for all.
   * @option reset
   *   Clear local prototype Drupal tables before queueing.
   * @option reset-pgvector
   *   Clear the external pgvector tables before embedding.
   * @option track
   *   Track indexed organizations for incremental entity-save queueing.
   * @option process-batch
   *   Number of queued documents to render per batch.
   * @option embed-batch
   *   Number of chunks to embed per batch.
   * @option model
   *   Ollama embedding model.
   * @option ollama-url
   *   Ollama base URL reachable from the DDEV web container.
   * @option pg-dsn
   *   PostgreSQL PDO DSN for the pgvector database.
   * @option pg-user
   *   PostgreSQL username.
   * @option pg-pass
   *   PostgreSQL password.
   * @usage drush mass-ai-editorial:index-all-orgs --reset --reset-pgvector
   *   Rebuild the local AI editorial index for all published organizations.
   */
  public function indexAllOrganizations(array $options = [
    'org-limit' => 0,
    'content-limit' => 0,
    'reset' => FALSE,
    'reset-pgvector' => FALSE,
    'track' => TRUE,
    'process-batch' => 100,
    'embed-batch' => 100,
    'model' => 'nomic-embed-text',
    'ollama-url' => 'http://host.docker.internal:11434',
    'pg-dsn' => 'pgsql:host=pgvector;port=5432;dbname=ai_editorial',
    'pg-user' => 'ai_editorial',
    'pg-pass' => 'ai_editorial',
  ]): void {
    if ((bool) $options['reset']) {
      $this->repository->reset();
      $this->output()->writeln('<comment>Reset AI editorial prototype tables.</comment>');
    }

    if ((bool) $options['reset-pgvector']) {
      $pg = $this->connectPgvector(
        (string) $options['pg-dsn'],
        (string) $options['pg-user'],
        (string) $options['pg-pass'],
      );
      $this->resetPgvector($pg);
      $this->output()->writeln('<comment>Reset pgvector AI editorial tables.</comment>');
    }

    $org_limit = (int) $options['org-limit'];
    $content_limit = (int) $options['content-limit'];
    $process_batch = max(1, (int) $options['process-batch']);
    $embed_batch = max(1, (int) $options['embed-batch']);
    $org_ids = $this->loadPublishedOrganizationIds($org_limit > 0 ? $org_limit : NULL);

    if (!$org_ids) {
      $this->output()->writeln('<comment>No published organization pages were found.</comment>');
      return;
    }

    if ((bool) $options['track']) {
      $tracked = $this->state->get('mass_ai_editorial.tracked_org_ids', []);
      $tracked = array_merge(array_map('intval', $tracked), $org_ids);
      $this->state->set('mass_ai_editorial.tracked_org_ids', array_values(array_unique($tracked)));
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $queued = 0;
    $seen_node_ids = [];

    foreach ($org_ids as $org_id) {
      $node_ids = $this->loadOrganizationNodeIds($org_id, $content_limit > 0 ? $content_limit : NULL);
      $node_ids = array_values(array_unique($node_ids));
      $nodes = $storage->loadMultiple($node_ids);
      $org_queued = 0;

      foreach ($node_ids as $node_id) {
        if (isset($seen_node_ids[$node_id])) {
          continue;
        }
        $seen_node_ids[$node_id] = TRUE;

        $node = $nodes[$node_id] ?? NULL;
        if ($node instanceof NodeInterface && $this->indexer->queueNode($node, $org_id) !== NULL) {
          $queued++;
          $org_queued++;
        }
      }

      $this->output()->writeln(sprintf('org:%d queued=%d', $org_id, $org_queued));
    }

    $this->output()->writeln(sprintf('<info>Queued %d unique node(s) across %d organization(s).</info>', $queued, count($org_ids)));

    $processed_total = 0;
    do {
      $processed = $this->processQueuedBatch($process_batch, FALSE);
      $processed_total += $processed;
    } while ($processed > 0);
    $this->output()->writeln(sprintf('<info>Rendered and chunked %d document(s).</info>', $processed_total));

    $embedded_total = 0;
    do {
      $embedded = $this->embedOllamaBatch([
        'limit' => $embed_batch,
        'model' => $options['model'],
        'ollama-url' => $options['ollama-url'],
        'pg-dsn' => $options['pg-dsn'],
        'pg-user' => $options['pg-user'],
        'pg-pass' => $options['pg-pass'],
      ]);
      $embedded_total += $embedded;
    } while ($embedded > 0);
    $this->output()->writeln(sprintf('<info>Embedded %d chunk(s) with %s.</info>', $embedded_total, (string) $options['model']));
  }

  /**
   * Render queued documents into canonical text and chunks.
   *
   * @command mass-ai-editorial:process-queue
   * @aliases maie-process
   * @option limit
   *   Maximum number of queued rows to process.
   * @usage drush mass-ai-editorial:process-queue --limit=25
   *   Render and chunk 25 queued documents.
   */
  public function processQueue(array $options = ['limit' => 25]): void {
    $processed = $this->processQueuedBatch((int) $options['limit']);
    $this->output()->writeln(sprintf('<info>Processed %d queued document(s).</info>', $processed));
  }

  private function processQueuedBatch(int $limit, bool $include_failed = TRUE): int {
    $documents = $this->repository->loadQueuedDocuments($limit, $include_failed);
    $storage = $this->entityTypeManager->getStorage('node');
    $processed = 0;

    foreach ($documents as $document) {
      try {
        $node = $storage->load((int) $document->entity_id);
        if (!$node instanceof NodeInterface || !$node->isPublished()) {
          $this->repository->markFailed((int) $document->id, 'Node is missing or unpublished.');
          continue;
        }

        $result = $this->indexer->processQueuedDocument($document, $node);
        $processed++;
        $this->output()->writeln(sprintf(
          'node:%d %s words=%d chunks=%d hash=%s',
          $node->id(),
          $node->label(),
          $result['words'],
          $result['chunks'],
          substr($result['hash'], 0, 12)
        ));
      }
      catch (\Throwable $exception) {
        $this->repository->markFailed((int) $document->id, $exception->getMessage());
        $this->logger()->error($exception->getMessage());
      }
    }

    return $processed;
  }

  /**
   * Print current prototype table counts.
   *
   * @command mass-ai-editorial:stats
   * @aliases maie-stats
   */
  public function stats(): void {
    $stats = $this->repository->stats();
    foreach ($stats['documents'] as $status => $count) {
      $this->output()->writeln(sprintf('%s: %d', $status, $count));
    }
    $this->output()->writeln(sprintf('chunks: %d', $stats['chunks']));
    $this->output()->writeln(sprintf('embedded chunks: %d', $stats['embedded_chunks']));
  }

  /**
   * Generate Ollama embeddings and upsert them into local pgvector.
   *
   * @command mass-ai-editorial:embed-ollama
   * @aliases maie-embed
   * @option limit
   *   Maximum number of chunks to embed.
   * @option model
   *   Ollama embedding model.
   * @option ollama-url
   *   Ollama base URL reachable from the DDEV web container.
   * @option pg-dsn
   *   PostgreSQL PDO DSN for the pgvector database.
   * @option pg-user
   *   PostgreSQL username.
   * @option pg-pass
   *   PostgreSQL password.
   * @usage drush mass-ai-editorial:embed-ollama --limit=25
   *   Embed 25 local chunks with nomic-embed-text and upsert them into pgvector.
   */
  public function embedOllama(array $options = [
    'limit' => 25,
    'model' => 'nomic-embed-text',
    'ollama-url' => 'http://host.docker.internal:11434',
    'pg-dsn' => 'pgsql:host=pgvector;port=5432;dbname=ai_editorial',
    'pg-user' => 'ai_editorial',
    'pg-pass' => 'ai_editorial',
  ]): void {
    $embedded = $this->embedOllamaBatch($options);
    if ($embedded > 0) {
      $this->output()->writeln(sprintf('<info>Embedded %d chunk(s) with %s.</info>', $embedded, (string) $options['model']));
    }
  }

  private function embedOllamaBatch(array $options): int {
    $limit = (int) $options['limit'];
    $model = (string) $options['model'];
    $chunks = $this->repository->loadChunksForEmbedding($model, $limit);
    if (!$chunks) {
      $this->output()->writeln('<info>No chunks need embeddings.</info>');
      return 0;
    }

    $pg = $this->connectPgvector(
      (string) $options['pg-dsn'],
      (string) $options['pg-user'],
      (string) $options['pg-pass'],
    );

    $embedded = 0;
    foreach ($chunks as $chunk) {
      $embedding = $this->generateOllamaEmbedding((string) $options['ollama-url'], $model, $chunk->chunk_text);
      if (count($embedding) !== 768) {
        throw new \RuntimeException(sprintf('Expected a 768-dimensional embedding from %s, got %d.', $model, count($embedding)));
      }

      $pg_document_id = $this->upsertPgDocument($pg, $chunk);
      $this->deleteStalePgChunks($pg, $pg_document_id, $this->repository->loadChunkDeltas((int) $chunk->local_document_id));
      $pg_chunk_id = $this->upsertPgChunk($pg, $pg_document_id, $chunk, $model, $embedding);
      $this->repository->markChunkEmbedded((int) $chunk->chunk_id, $model, (string) $pg_chunk_id);
      $embedded++;

      $this->output()->writeln(sprintf(
        'embedded node:%d chunk:%d pg_chunk:%d %s',
        $chunk->entity_id,
        $chunk->chunk_delta,
        $pg_chunk_id,
        mb_substr((string) $chunk->title, 0, 72)
      ));
    }

    return $embedded;
  }

  /**
   * Print document and chunk counts from the pgvector database.
   *
   * @command mass-ai-editorial:pgvector-stats
   * @aliases maie-pgstats
   * @option pg-dsn
   *   PostgreSQL PDO DSN for the pgvector database.
   * @option pg-user
   *   PostgreSQL username.
   * @option pg-pass
   *   PostgreSQL password.
   */
  public function pgvectorStats(array $options = [
    'pg-dsn' => 'pgsql:host=pgvector;port=5432;dbname=ai_editorial',
    'pg-user' => 'ai_editorial',
    'pg-pass' => 'ai_editorial',
  ]): void {
    $pg = $this->connectPgvector(
      (string) $options['pg-dsn'],
      (string) $options['pg-user'],
      (string) $options['pg-pass'],
    );

    $documents = (int) $pg->query('SELECT COUNT(*) FROM ai_document')->fetchColumn();
    $chunks = (int) $pg->query('SELECT COUNT(*) FROM ai_document_chunk')->fetchColumn();
    $embedded = (int) $pg->query('SELECT COUNT(*) FROM ai_document_chunk WHERE embedding IS NOT NULL')->fetchColumn();

    $this->output()->writeln(sprintf('pgvector documents: %d', $documents));
    $this->output()->writeln(sprintf('pgvector chunks: %d', $chunks));
    $this->output()->writeln(sprintf('pgvector embedded chunks: %d', $embedded));
  }

  /**
   * Search pgvector with a local Ollama embedding.
   *
   * @command mass-ai-editorial:search
   * @aliases maie-search
   * @param string $query_text
   *   Plain-English search text.
   * @option limit
   *   Number of nearest chunks to return.
   * @option model
   *   Ollama embedding model.
   * @option ollama-url
   *   Ollama base URL reachable from the DDEV web container.
   * @option pg-dsn
   *   PostgreSQL PDO DSN for the pgvector database.
   * @option pg-user
   *   PostgreSQL username.
   * @option pg-pass
   *   PostgreSQL password.
   * @usage drush mass-ai-editorial:search "overpayment waiver" --limit=5
   *   Find chunks semantically close to "overpayment waiver".
   */
  public function searchOllama(string $query_text, array $options = [
    'limit' => 5,
    'model' => 'nomic-embed-text',
    'ollama-url' => 'http://host.docker.internal:11434',
    'pg-dsn' => 'pgsql:host=pgvector;port=5432;dbname=ai_editorial',
    'pg-user' => 'ai_editorial',
    'pg-pass' => 'ai_editorial',
  ]): void {
    $embedding = $this->generateOllamaEmbedding((string) $options['ollama-url'], (string) $options['model'], $query_text);
    $pg = $this->connectPgvector(
      (string) $options['pg-dsn'],
      (string) $options['pg-user'],
      (string) $options['pg-pass'],
    );

    $statement = $pg->prepare(
      'SELECT
        d.drupal_entity_id,
        d.title,
        d.url,
        c.chunk_delta,
        1 - (c.embedding <=> CAST(:embedding AS vector)) AS similarity
      FROM ai_document_chunk c
      INNER JOIN ai_document d ON d.id = c.document_id
      WHERE c.embedding IS NOT NULL
      ORDER BY c.embedding <=> CAST(:embedding AS vector)
      LIMIT :limit'
    );
    $statement->bindValue(':embedding', '[' . implode(',', $embedding) . ']');
    $statement->bindValue(':limit', (int) $options['limit'], \PDO::PARAM_INT);
    $statement->execute();

    foreach ($statement->fetchAll() as $row) {
      $this->output()->writeln(sprintf(
        '%.3f node:%d chunk:%d %s %s',
        (float) $row['similarity'],
        (int) $row['drupal_entity_id'],
        (int) $row['chunk_delta'],
        $row['title'],
        $row['url'] ?? ''
      ));
    }
  }

  /**
   * Loads nodes matching the provided admin search organization slice.
   */
  private function loadPocNodeIds(int $org_id, int $limit): array {
    return $this->loadOrganizationNodeIds($org_id, $limit);
  }

  /**
   * Loads published organization page node IDs.
   *
   * @return array<int>
   *   Organization node IDs.
   */
  private function loadPublishedOrganizationIds(?int $limit = NULL): array {
    $query = $this->database->select('node_field_data', 'n');
    $query->fields('n', ['nid']);
    $query->condition('n.status', 1);
    $query->condition('n.type', 'org_page');
    $query->condition('n.default_langcode', 1);
    $query->orderBy('n.title');
    if ($limit !== NULL) {
      $query->range(0, $limit);
    }

    return array_map('intval', $query->execute()->fetchCol());
  }

  /**
   * Loads published nodes for one organization.
   *
   * @return array<int>
   *   Node IDs.
   */
  private function loadOrganizationNodeIds(int $org_id, ?int $limit = NULL): array {
    $query = $this->database->select('node_field_data', 'n');
    $query->distinct();
    $query->leftJoin('node__field_organizations', 'o', 'n.nid = o.entity_id');
    $query->fields('n', ['nid', 'changed']);
    $query->condition('n.status', 1);
    $query->condition('n.type', AiEditorialIndexer::EXCLUDED_BUNDLES, 'NOT IN');
    $query->condition('n.default_langcode', 1);
    $or = $query->orConditionGroup()
      ->condition('n.nid', $org_id)
      ->condition('o.field_organizations_target_id', $org_id);
    $query->condition($or);
    $query->orderBy('n.changed', 'DESC');
    if ($limit !== NULL) {
      $query->range(0, $limit);
    }

    return array_map('intval', $query->execute()->fetchCol());
  }

  private function connectPgvector(string $dsn, string $user, string $pass): \PDO {
    return new \PDO($dsn, $user, $pass, [
      \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
      \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    ]);
  }

  private function resetPgvector(\PDO $pg): void {
    $pg->exec('TRUNCATE TABLE ai_document_chunk, ai_document RESTART IDENTITY');
  }

  /**
   * Requests one embedding from Ollama.
   *
   * @return array<int, float>
   *   The embedding vector.
   */
  private function generateOllamaEmbedding(string $ollama_url, string $model, string $text): array {
    $response = $this->httpClient->request('POST', rtrim($ollama_url, '/') . '/api/embed', [
      'json' => [
        'model' => $model,
        'input' => $text,
      ],
      'timeout' => 120,
    ]);
    $payload = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
    $embedding = $payload['embeddings'][0] ?? NULL;
    if (!is_array($embedding)) {
      throw new \RuntimeException('Ollama did not return an embedding.');
    }

    return array_map('floatval', $embedding);
  }

  private function upsertPgDocument(\PDO $pg, object $chunk): int {
    $statement = $pg->prepare(
      'INSERT INTO ai_document (
        drupal_entity_type, drupal_entity_id, drupal_revision_id, langcode,
        org_id, bundle, title, url, content_hash, indexed_at
      )
      VALUES (
        :entity_type, :entity_id, :revision_id, :langcode,
        :org_id, :bundle, :title, :url, :content_hash, now()
      )
      ON CONFLICT (drupal_entity_type, drupal_entity_id, langcode)
      DO UPDATE SET
        drupal_revision_id = EXCLUDED.drupal_revision_id,
        org_id = EXCLUDED.org_id,
        bundle = EXCLUDED.bundle,
        title = EXCLUDED.title,
        url = EXCLUDED.url,
        content_hash = EXCLUDED.content_hash,
        indexed_at = now()
      RETURNING id'
    );
    $statement->execute([
      ':entity_type' => $chunk->entity_type,
      ':entity_id' => (int) $chunk->entity_id,
      ':revision_id' => $chunk->revision_id !== NULL ? (int) $chunk->revision_id : NULL,
      ':langcode' => $chunk->langcode,
      ':org_id' => $chunk->org_id !== NULL ? (int) $chunk->org_id : NULL,
      ':bundle' => $chunk->bundle,
      ':title' => $chunk->title,
      ':url' => $chunk->url,
      ':content_hash' => $chunk->content_hash,
    ]);

    return (int) $statement->fetchColumn();
  }

  /**
   * Upserts a chunk and its vector into pgvector.
   *
   * @param array<int, float> $embedding
   *   Embedding vector.
   */
  private function upsertPgChunk(\PDO $pg, int $pg_document_id, object $chunk, string $model, array $embedding): int {
    $statement = $pg->prepare(
      'INSERT INTO ai_document_chunk (
        document_id, chunk_delta, chunk_hash, heading, text,
        token_estimate, embedding_model, embedding, embedded_at
      )
      VALUES (
        :document_id, :chunk_delta, :chunk_hash, :heading, :text,
        :token_estimate, :embedding_model, CAST(:embedding AS vector), now()
      )
      ON CONFLICT (document_id, chunk_delta)
      DO UPDATE SET
        chunk_hash = EXCLUDED.chunk_hash,
        heading = EXCLUDED.heading,
        text = EXCLUDED.text,
        token_estimate = EXCLUDED.token_estimate,
        embedding_model = EXCLUDED.embedding_model,
        embedding = EXCLUDED.embedding,
        embedded_at = now()
      RETURNING id'
    );
    $statement->execute([
      ':document_id' => $pg_document_id,
      ':chunk_delta' => (int) $chunk->chunk_delta,
      ':chunk_hash' => $chunk->chunk_hash,
      ':heading' => $chunk->heading,
      ':text' => $chunk->chunk_text,
      ':token_estimate' => (int) $chunk->token_estimate,
      ':embedding_model' => $model,
      ':embedding' => '[' . implode(',', $embedding) . ']',
    ]);

    return (int) $statement->fetchColumn();
  }

  /**
   * Removes pgvector chunks that are no longer present in the latest render.
   *
   * @param array<int> $current_deltas
   *   Current local chunk deltas for the document.
   */
  private function deleteStalePgChunks(\PDO $pg, int $pg_document_id, array $current_deltas): void {
    if (!$current_deltas) {
      $statement = $pg->prepare('DELETE FROM ai_document_chunk WHERE document_id = :document_id');
      $statement->execute([':document_id' => $pg_document_id]);
      return;
    }

    $placeholders = [];
    $values = [':document_id' => $pg_document_id];
    foreach (array_values(array_unique(array_map('intval', $current_deltas))) as $delta_index => $delta) {
      $placeholder = ':delta_' . $delta_index;
      $placeholders[] = $placeholder;
      $values[$placeholder] = $delta;
    }

    $statement = $pg->prepare(sprintf(
      'DELETE FROM ai_document_chunk WHERE document_id = :document_id AND chunk_delta NOT IN (%s)',
      implode(', ', $placeholders)
    ));
    $statement->execute($values);
  }

}
