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
  private const PARENT_CONTEXT_MAX_CHARS = 8000;
  private const BODY_LINK_CONTEXT_MAX_LINKS = 6;
  private const BODY_LINK_CONTEXT_MAX_CHARS = 3000;

  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * Builds prompt context containing related indexed page excerpts.
   */
  public function buildForNode(int $node_id, int $page_limit = 5, int $max_chunks_per_page = 3): string {
    $current_document = $this->loadIndexedDocumentByEntityId($node_id);
    $parent_document = $this->loadBreadcrumbParentDocument($current_document);
    $body_link_documents = $this->loadBodyLinkDocuments($current_document, $parent_document);
    $rows = $this->loadRelatedChunks($node_id, $parent_document ? $page_limit + 1 : $page_limit, $max_chunks_per_page);
    if (!$rows && !$parent_document && !$body_link_documents) {
      return '';
    }

    $grouped = [];
    foreach ($rows as $row) {
      $candidate_id = (int) $row['candidate_node_id'];
      if ($parent_document && $candidate_id === (int) $parent_document->entity_id) {
        continue;
      }
      if (isset($body_link_documents[$candidate_id])) {
        continue;
      }

      $grouped[$candidate_id]['title'] = $row['candidate_title'];
      $grouped[$candidate_id]['url'] = $row['candidate_url'];
      $grouped[$candidate_id]['similarity'] = max((float) ($grouped[$candidate_id]['similarity'] ?? 0), (float) $row['similarity']);
      $grouped[$candidate_id]['structure_summary'] ??= $this->buildIndexedStructureSummary($candidate_id);
      $grouped[$candidate_id]['chunks'][] = [
        'delta' => (int) $row['candidate_chunk_delta'],
        'similarity' => (float) $row['similarity'],
        'text' => $this->trimExcerpt($this->removeBoilerplate((string) $row['candidate_text'])),
      ];
    }
    $grouped = array_slice($grouped, 0, $page_limit, TRUE);

    $context = '';
    if ($parent_document) {
      $context .= sprintf(
        "Breadcrumb parent page context:\nTitle: %s\nNode ID: %d\nURL: %s\nIndexed structure summary:\n%s\nFull indexed parent page text:\n%s\n\n",
        $parent_document->title,
        (int) $parent_document->entity_id,
        $parent_document->url,
        $this->buildStructureSummaryFromText((string) $parent_document->rendered_text),
        $this->trimParentContext($this->removeBoilerplate((string) $parent_document->rendered_text))
      );
    }

    if ($body_link_documents) {
      $context .= "Body-linked page context:\n";
      $context .= "These indexed pages are linked from the current page's main body area. Treat them as intentional editorial context selected by the author, not as automatically discovered related pages.\n\n";
      foreach ($body_link_documents as $document) {
        $context .= sprintf(
          "Body-linked page\nTitle: %s\nNode ID: %d\nURL: %s\nIndexed structure summary:\n%s\nIndexed page excerpt:\n%s\n\n",
          $document->title,
          (int) $document->entity_id,
          $document->url,
          $this->buildStructureSummaryFromText((string) $document->rendered_text),
          $this->trimBodyLinkContext($this->removeBoilerplate((string) $document->rendered_text))
        );
      }
    }

    if (!$grouped) {
      return trim($context);
    }

    $context .= "Related pages for the contextual page check:\n";
    $context .= "These pages were retrieved from the local vector index because their indexed text is semantically similar to the current page. Treat them as pages to review, not as confirmed problems.\n\n";

    $candidate_number = 1;
    foreach ($grouped as $candidate_id => $candidate) {
      $context .= sprintf(
        "Related page %d of %d\nTitle: %s\nNode ID: %d\nURL: %s\nSimilarity score: %.3f\nIndexed structure summary:\n%s\n",
        $candidate_number,
        count($grouped),
        $candidate['title'],
        $candidate_id,
        $candidate['url'] ?? '',
        $candidate['similarity'],
        $candidate['structure_summary'] ?: 'No indexed structure summary available.'
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
   * Loads the indexed AI document for a node.
   */
  private function loadIndexedDocumentByEntityId(int $node_id): ?object {
    if (!$this->database->schema()->tableExists('mass_ai_editorial_document')) {
      return NULL;
    }

    $document = $this->database->select('mass_ai_editorial_document', 'd')
      ->fields('d', ['entity_id', 'title', 'url', 'rendered_text'])
      ->condition('entity_type', 'node')
      ->condition('entity_id', $node_id)
      ->condition('view_mode', 'ai_index')
      ->condition('status', 'deleted', '<>')
      ->execute()
      ->fetchObject();

    return $document ?: NULL;
  }

  /**
   * Builds a compact structure summary from one indexed document.
   */
  private function buildIndexedStructureSummary(int $node_id): string {
    $document = $this->loadIndexedDocumentByEntityId($node_id);
    if (!$document || empty($document->rendered_text)) {
      return '';
    }

    return $this->buildStructureSummaryFromText((string) $document->rendered_text);
  }

  /**
   * Extracts structural signals that should be visible for context pages.
   */
  private function buildStructureSummaryFromText(string $text): string {
    $lines = [];
    if (preg_match('/^Breadcrumb: (?<breadcrumb>.*)$/m', $text, $matches)) {
      $lines[] = 'Breadcrumb: ' . trim($matches['breadcrumb']);
    }

    $offered_by = $this->extractOfferedByLines($text);
    if ($offered_by) {
      $lines[] = 'Offered by links from rendered view mode:';
      foreach ($offered_by as $line) {
        $lines[] = '- ' . $line;
      }
    }

    $headings = $this->extractHeadingLines($text);
    if ($headings) {
      $lines[] = 'Semantic heading markers:';
      foreach ($headings as $line) {
        $lines[] = '- ' . $line;
      }
    }

    $links = $this->extractLinkTargetExamples($text);
    if ($links) {
      $lines[] = 'Link targets preserved in indexed text:';
      foreach ($links as $line) {
        $lines[] = '- ' . $line;
      }
    }

    return $lines ? implode("\n", $lines) : 'No breadcrumb, offered-by links, headings, or link targets found in indexed text.';
  }

  /**
   * Extracts Offered by links from rendered indexed text.
   *
   * @return array<int, string>
   *   Link-aware labels.
   */
  private function extractOfferedByLines(string $text): array {
    if (!preg_match('/\noffered by\n(?<offered>.*?)(?:\n\n|Heading level 1:|Heading level 2:|$)/is', $text, $matches)) {
      return [];
    }

    preg_match_all('/^(?<line>.+?\[href: [^\]]+\])$/m', trim($matches['offered']), $link_matches);
    return array_slice(array_values(array_unique(array_map('trim', $link_matches['line'] ?? []))), 0, 8);
  }

  /**
   * Extracts semantic H2/H3 heading markers from indexed text.
   *
   * @return array<int, string>
   *   Heading marker lines.
   */
  private function extractHeadingLines(string $text): array {
    preg_match_all('/^Heading level (?<level>[23]):\s*(?<heading>.+)$/m', $text, $matches, PREG_SET_ORDER);
    $headings = [];
    foreach ($matches as $match) {
      $headings[] = 'Heading level ' . (int) $match['level'] . ': ' . trim($match['heading']);
    }

    return array_slice(array_values(array_unique($headings)), 0, 12);
  }

  /**
   * Extracts example preserved link targets from indexed text.
   *
   * @return array<int, string>
   *   Link-aware labels.
   */
  private function extractLinkTargetExamples(string $text): array {
    $text = preg_replace('/^Breadcrumb: .*$/m', '', $text) ?? $text;
    preg_match_all('/(?<label>[^\n\[]+?)\s*\[href: (?<href>[^\]]+)\]/', $text, $matches, PREG_SET_ORDER);
    $links = [];
    foreach ($matches as $match) {
      $label = trim(preg_replace('/\s+/', ' ', $match['label']) ?? $match['label']);
      $href = trim($match['href']);
      if ($label === '' || $href === '' || str_starts_with($label, 'Breadcrumb:')) {
        continue;
      }
      $links[$href] = $label . ' [href: ' . $href . ']';
      if (count($links) >= 12) {
        break;
      }
    }

    return array_values($links);
  }

  /**
   * Loads indexed pages linked from the current page's main body area.
   */
  private function loadBodyLinkDocuments(?object $current_document, ?object $parent_document): array {
    if (!$current_document || empty($current_document->rendered_text)) {
      return [];
    }

    $hrefs = $this->findBodyLinkHrefs((string) $current_document->rendered_text, (string) ($current_document->url ?? ''));
    if (!$hrefs || count($hrefs) > self::BODY_LINK_CONTEXT_MAX_LINKS) {
      return [];
    }

    $documents = [];
    foreach ($hrefs as $href) {
      if ($parent_document && $href === (string) $parent_document->url) {
        continue;
      }

      $document = $this->loadIndexedDocumentByUrl($href);
      if ($document) {
        $documents[(int) $document->entity_id] = $document;
      }
    }

    return $documents;
  }

  /**
   * Finds a small set of internal links from the current page's main body.
   */
  private function findBodyLinkHrefs(string $text, string $current_url): array {
    $body = $this->extractMainBodyText($text);
    if ($body === '') {
      return [];
    }

    preg_match_all('/\[href: (?<href>[^\]]+)\]/', $body, $matches);
    $hrefs = [];
    foreach ($matches['href'] ?? [] as $href) {
      $href = strtok(trim($href), '#') ?: trim($href);
      if (!$this->isIndexableInternalPageHref($href, $current_url)) {
        continue;
      }

      $hrefs[] = $href;
    }

    return array_values(array_unique($hrefs));
  }

  /**
   * Extracts a conservative approximation of the authored main body area.
   */
  private function extractMainBodyText(string $text): string {
    $body = $text;
    if (preg_match('/^(?:Heading level [1-6]:\s*)?The Details$/m', $body, $matches, PREG_OFFSET_CAPTURE)) {
      $body = substr($body, $matches[0][1]);
    }

    $body = preg_replace('/Table of Contents.*?(?:\n\n)(?=Heading level [1-6]:|[^\n]+\n\n)/s', '', $body, 1) ?? $body;
    $stop_patterns = [
      '/\n(?:Heading level [1-6]:\s*)?Downloads\n/is',
      '/\n(?:Heading level [1-6]:\s*)?Contact\n/is',
      '/\n(?:Heading level [1-6]:\s*)?Contacts\n/is',
      '/\n(?:Heading level [1-6]:\s*)?Related(?: information| links| services)?\n/is',
    ];
    foreach ($stop_patterns as $pattern) {
      if (preg_match($pattern, $body, $matches, PREG_OFFSET_CAPTURE)) {
        $body = substr($body, 0, $matches[0][1]);
      }
    }

    return trim($body);
  }

  /**
   * Returns TRUE when an href can be resolved to an indexed Mass.gov page.
   */
  private function isIndexableInternalPageHref(string $href, string $current_url): bool {
    if ($href === '' || $href === $current_url || str_starts_with($href, '#')) {
      return FALSE;
    }
    if (preg_match('/^(?:https?:|mailto:|tel:)/i', $href)) {
      return FALSE;
    }
    if (!str_starts_with($href, '/')) {
      return FALSE;
    }
    if (preg_match('@^/(?:doc|media|files?)/@', $href)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Loads an indexed document by canonical URL.
   */
  private function loadIndexedDocumentByUrl(string $url): ?object {
    if (!$this->database->schema()->tableExists('mass_ai_editorial_document')) {
      return NULL;
    }

    $document = $this->database->select('mass_ai_editorial_document', 'd')
      ->fields('d', ['entity_id', 'title', 'url', 'rendered_text'])
      ->condition('entity_type', 'node')
      ->condition('url', $url)
      ->condition('view_mode', 'ai_index')
      ->condition('status', 'deleted', '<>')
      ->execute()
      ->fetchObject();

    return $document ?: NULL;
  }

  /**
   * Loads the immediate breadcrumb parent page from the indexed document table.
   */
  private function loadBreadcrumbParentDocument(?object $current_document): ?object {
    if (!$current_document || empty($current_document->rendered_text)) {
      return NULL;
    }

    $parent_href = $this->findBreadcrumbParentHref((string) $current_document->rendered_text, (string) ($current_document->url ?? ''));
    if ($parent_href === '') {
      return NULL;
    }

    $document = $this->database->select('mass_ai_editorial_document', 'd')
      ->fields('d', ['entity_id', 'title', 'url', 'rendered_text'])
      ->condition('entity_type', 'node')
      ->condition('url', $parent_href)
      ->condition('view_mode', 'ai_index')
      ->condition('status', 'deleted', '<>')
      ->execute()
      ->fetchObject();

    return $document ?: NULL;
  }

  /**
   * Finds the closest linked ancestor in the current page breadcrumb line.
   */
  private function findBreadcrumbParentHref(string $text, string $current_url): string {
    if (!preg_match('/^Breadcrumb: (?<breadcrumb>.*)$/m', $text, $matches)) {
      return '';
    }

    preg_match_all('/\[href: (?<href>[^\]]+)\]/', $matches['breadcrumb'], $href_matches);
    $hrefs = array_reverse($href_matches['href'] ?? []);
    foreach ($hrefs as $href) {
      $href = trim($href);
      if ($href !== '' && $href !== '/' && $href !== $current_url) {
        return $href;
      }
    }

    return '';
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

  private function trimParentContext(string $text): string {
    $text = preg_replace('/\s+/', ' ', trim($text)) ?? trim($text);
    if (mb_strlen($text) <= self::PARENT_CONTEXT_MAX_CHARS) {
      return $text;
    }

    return mb_substr($text, 0, self::PARENT_CONTEXT_MAX_CHARS) . "\n\n[Parent page text truncated for synchronous analysis.]";
  }

  private function trimBodyLinkContext(string $text): string {
    $text = preg_replace('/\s+/', ' ', trim($text)) ?? trim($text);
    if (mb_strlen($text) <= self::BODY_LINK_CONTEXT_MAX_CHARS) {
      return $text;
    }

    return mb_substr($text, 0, self::BODY_LINK_CONTEXT_MAX_CHARS) . "\n\n[Body-linked page text truncated for synchronous analysis.]";
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
