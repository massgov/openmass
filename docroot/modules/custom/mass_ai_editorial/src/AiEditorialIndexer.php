<?php

namespace Drupal\mass_ai_editorial;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\node\NodeInterface;

/**
 * Coordinates rendered canonical text indexing for changed nodes.
 */
class AiEditorialIndexer {

  public const EXCLUDED_BUNDLES = [
    'alert',
    'contact_information',
    'person',
    'event',
    'news',
    'decision_tree_branch',
    'glossary',
    'decision_tree_conclusion',
    'api_service_card',
    'external_data_resource',
    'fee',
  ];

  public function __construct(
    private readonly RenderedTextExtractor $textExtractor,
    private readonly TextChunker $chunker,
    private readonly AiEditorialIndexRepository $repository,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Queues a node if it is in scope for editorial AI indexing.
   */
  public function queueNode(NodeInterface $node, ?int $org_id = NULL, string $view_mode = 'ai_index'): ?int {
    if (in_array($node->bundle(), self::EXCLUDED_BUNDLES, TRUE)) {
      return NULL;
    }

    $org_id ??= $this->primaryOrgId($node);
    $url = $node->hasLinkTemplate('canonical') ? $node->toUrl('canonical', ['absolute' => FALSE])->toString() : NULL;

    return $this->repository->queueDocument([
      'entity_type' => 'node',
      'entity_id' => (int) $node->id(),
      'revision_id' => $node->getRevisionId() ? (int) $node->getRevisionId() : NULL,
      'langcode' => $node->language()->getId(),
      'org_id' => $org_id,
      'bundle' => $node->bundle(),
      'title' => mb_substr($node->label(), 0, 512),
      'url' => $url,
      'view_mode' => $view_mode,
      'status' => 'queued',
      'queued_at' => $this->time->getRequestTime(),
      'changed_at' => (int) ($node->getChangedTime() ?: $this->time->getRequestTime()),
    ]);
  }

  /**
   * Renders and chunks one already-queued document row.
   */
  public function processQueuedDocument(object $document, NodeInterface $node): array {
    $text = $this->textExtractor->extract($node, $document->view_mode);
    $chunks = $this->chunker->chunk($text);
    $this->repository->storeRenderedDocument((int) $document->id, $text, $chunks);

    return [
      'words' => str_word_count($text),
      'chunks' => count($chunks),
      'hash' => hash('sha256', $text),
    ];
  }

  private function primaryOrgId(NodeInterface $node): ?int {
    if ((string) $node->bundle() === 'org_page') {
      return (int) $node->id();
    }
    if (!$node->hasField('field_organizations') || $node->get('field_organizations')->isEmpty()) {
      return NULL;
    }

    $value = $node->get('field_organizations')->first()?->getValue();
    return isset($value['target_id']) ? (int) $value['target_id'] : NULL;
  }

}
