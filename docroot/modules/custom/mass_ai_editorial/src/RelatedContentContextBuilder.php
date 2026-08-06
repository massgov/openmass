<?php

namespace Drupal\mass_ai_editorial;

use Drupal\Core\Database\Connection;

/**
 * Builds pgvector-backed context for AI Content Advisor reports.
 */
class RelatedContentContextBuilder {

  private const PG_DSN = 'pgsql:host=pgvector;port=5432;dbname=ai_editorial';
  private const PG_USER = 'ai_editorial';
  private const PG_PASS = 'ai_editorial';
  private const SECOND_CHUNK_SIMILARITY_DELTA = 0.04;
  private const THIRD_CHUNK_SIMILARITY_DELTA = 0.06;

  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * Builds prompt context containing related indexed page excerpts.
   */
  public function buildForNode(int $node_id, int $page_limit = 5, int $max_chunks_per_page = 3): string {
    $rows = $this->loadRelatedChunks($node_id, $page_limit, $max_chunks_per_page);
    if (!$rows) {
      return '';
    }

    $grouped = [];
    foreach ($rows as $row) {
      $candidate_id = (int) $row['candidate_node_id'];
      $grouped[$candidate_id]['title'] = $row['candidate_title'];
      $grouped[$candidate_id]['url'] = $row['candidate_url'];
      $grouped[$candidate_id]['similarity'] = max((float) ($grouped[$candidate_id]['similarity'] ?? 0), (float) $row['similarity']);
      $grouped[$candidate_id]['chunks'][] = [
        'delta' => (int) $row['candidate_chunk_delta'],
        'similarity' => (float) $row['similarity'],
        'text' => $this->trimExcerpt($this->removeBoilerplate((string) $row['candidate_text'])),
      ];
    }

    $context = "Related pages for the contextual page check:\n";
    $context .= "These pages were retrieved from the local vector index because their indexed text is semantically similar to the current page. Treat them as pages to review, not as confirmed problems.\n\n";

    $candidate_number = 1;
    foreach ($grouped as $candidate_id => $candidate) {
      $context .= sprintf(
        "Related page %d of %d\nTitle: %s\nNode ID: %d\nURL: %s\nSimilarity score: %.3f\n",
        $candidate_number,
        count($grouped),
        $candidate['title'],
        $candidate_id,
        $candidate['url'] ?? '',
        $candidate['similarity']
      );

      foreach ($this->selectAdaptiveChunks($candidate['chunks'], $max_chunks_per_page) as $chunk) {
        $context .= sprintf(
          "Indexed excerpt chunk %d (similarity %.3f):\n%s\n",
          $chunk['delta'],
          $chunk['similarity'],
          $chunk['text']
        );
      }

      $context .= "\n";
      $candidate_number++;
    }

    return $context;
  }

  /**
   * Loads nearest indexed chunks for a node, excluding the node itself.
   */
  private function loadRelatedChunks(int $node_id, int $page_limit, int $max_chunks_per_page): array {
    $pdo = $this->connect();
    $statement = $pdo->prepare(
      'WITH current_chunks AS (
        SELECT c.embedding
        FROM ai_document_chunk c
        INNER JOIN ai_document d ON d.id = c.document_id
        WHERE d.drupal_entity_id = :node_id
          AND c.embedding IS NOT NULL
      ),
      ranked AS (
        SELECT
          d.drupal_entity_id AS candidate_node_id,
          d.title AS candidate_title,
          d.url AS candidate_url,
          c.chunk_delta AS candidate_chunk_delta,
          c.text AS candidate_text,
          1 - MIN(c.embedding <=> current_chunks.embedding) AS similarity,
          ROW_NUMBER() OVER (
            PARTITION BY d.drupal_entity_id
            ORDER BY MIN(c.embedding <=> current_chunks.embedding)
          ) AS chunk_rank
        FROM current_chunks
        INNER JOIN ai_document_chunk c ON c.embedding IS NOT NULL
        INNER JOIN ai_document d ON d.id = c.document_id
        WHERE d.drupal_entity_id <> :node_id
          AND c.text NOT ILIKE :boilerplate_contacts
          AND c.text NOT ILIKE :boilerplate_login
        GROUP BY d.drupal_entity_id, d.title, d.url, c.id, c.chunk_delta, c.text
      ),
      page_rank AS (
        SELECT
          candidate_node_id,
          MAX(similarity) AS best_similarity,
          ROW_NUMBER() OVER (ORDER BY MAX(similarity) DESC) AS page_rank
        FROM ranked
        GROUP BY candidate_node_id
      )
      SELECT ranked.*
      FROM ranked
      INNER JOIN page_rank USING (candidate_node_id)
      WHERE page_rank.page_rank <= :page_limit
        AND ranked.chunk_rank <= :max_chunks_per_page
      ORDER BY page_rank.page_rank, ranked.chunk_rank'
    );
    $statement->bindValue(':node_id', $node_id, \PDO::PARAM_INT);
    $statement->bindValue(':page_limit', $page_limit, \PDO::PARAM_INT);
    $statement->bindValue(':max_chunks_per_page', $max_chunks_per_page, \PDO::PARAM_INT);
    $statement->bindValue(':boilerplate_contacts', '%Contacts Department of Unemployment Assistance%');
    $statement->bindValue(':boilerplate_login', '%Online Unemployment Services for Workers%');
    $statement->execute();

    return $statement->fetchAll();
  }

  /**
   * Keeps additional chunks only when they are close to the best match.
   */
  private function selectAdaptiveChunks(array $chunks, int $max_chunks_per_page): array {
    if (!$chunks) {
      return [];
    }

    usort($chunks, static fn(array $a, array $b): int => $b['similarity'] <=> $a['similarity']);

    $selected = [reset($chunks)];
    $best_similarity = (float) $selected[0]['similarity'];
    $thresholds = [
      1 => self::SECOND_CHUNK_SIMILARITY_DELTA,
      2 => self::THIRD_CHUNK_SIMILARITY_DELTA,
    ];

    foreach (array_slice($chunks, 1, max(0, $max_chunks_per_page - 1)) as $chunk) {
      $position = count($selected);
      $allowed_delta = $thresholds[$position] ?? self::THIRD_CHUNK_SIMILARITY_DELTA;
      if ($best_similarity - (float) $chunk['similarity'] > $allowed_delta) {
        continue;
      }

      $selected[] = $chunk;
    }

    usort($selected, static fn(array $a, array $b): int => $a['delta'] <=> $b['delta']);

    return $selected;
  }

  private function connect(): \PDO {
    return new \PDO(self::PG_DSN, self::PG_USER, self::PG_PASS, [
      \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
      \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    ]);
  }

  private function trimExcerpt(string $text): string {
    $text = preg_replace('/\s+/', ' ', trim($text)) ?? trim($text);
    if (mb_strlen($text) <= 1200) {
      return $text;
    }

    return mb_substr($text, 0, 1200) . '...';
  }

  private function removeBoilerplate(string $text): string {
    $patterns = [
      '/Contacts Department of Unemployment Assistance.*$/is',
      '/Help Us Improve Mass\\.gov.*$/is',
      '/Please let us know how we can improve this page.*$/is',
      '/Table of Contents.*?You skipped the table of contents section\\./is',
    ];

    return trim(preg_replace($patterns, ' ', $text) ?? $text);
  }

}
