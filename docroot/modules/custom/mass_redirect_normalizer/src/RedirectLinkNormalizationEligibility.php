<?php

namespace Drupal\mass_redirect_normalizer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mayflower\Helper;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Shared eligibility rules for bulk enqueue and queue workers.
 */
final class RedirectLinkNormalizationEligibility {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Checks if this entity should be processed by bulk normalization.
   */
  public function isEligible(
    string $entityType,
    object $entity,
    array &$nodePublishedCache = [],
    array &$newerDraftCache = [],
  ): bool {
    if ($entityType === 'node') {
      if (!$entity instanceof NodeInterface) {
        return FALSE;
      }
      $nodeId = (int) $entity->id();
      $isPublished = $nodePublishedCache[$nodeId] ?? $entity->isPublished();
      $nodePublishedCache[$nodeId] = $isPublished;
      if (!$isPublished) {
        return FALSE;
      }
      return !$this->hasNewerUnpublishedDraft($entity, $newerDraftCache);
    }

    if ($entityType === 'paragraph') {
      if (!$entity instanceof Paragraph) {
        return FALSE;
      }
      if (Helper::isParagraphOrphan($entity)) {
        return FALSE;
      }
      $parentNode = Helper::getParentNode($entity);
      if (!$parentNode instanceof NodeInterface) {
        return FALSE;
      }
      $parentNodeId = (int) $parentNode->id();
      $parentPublished = $nodePublishedCache[$parentNodeId] ?? $parentNode->isPublished();
      $nodePublishedCache[$parentNodeId] = $parentPublished;
      if (!$parentPublished) {
        return FALSE;
      }
      return !$this->hasNewerUnpublishedDraft($parentNode, $newerDraftCache);
    }

    return FALSE;
  }

  /**
   * Returns TRUE when latest node revision is unpublished and newer.
   */
  private function hasNewerUnpublishedDraft(NodeInterface $node, array &$cache): bool {
    $nodeId = (int) $node->id();
    if (array_key_exists($nodeId, $cache)) {
      return $cache[$nodeId];
    }

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('node');
    $latestRevisionId = $storage->getLatestRevisionId($node->id());
    if (!$latestRevisionId || (int) $latestRevisionId === (int) $node->getRevisionId()) {
      $cache[$nodeId] = FALSE;
      return $cache[$nodeId];
    }

    $revisions = $storage->loadMultipleRevisions([(int) $latestRevisionId]);
    $latest = $revisions[(int) $latestRevisionId] ?? NULL;
    if (!$latest instanceof NodeInterface) {
      $cache[$nodeId] = FALSE;
      return $cache[$nodeId];
    }

    $cache[$nodeId] = !$latest->isPublished();
    return $cache[$nodeId];
  }

}
