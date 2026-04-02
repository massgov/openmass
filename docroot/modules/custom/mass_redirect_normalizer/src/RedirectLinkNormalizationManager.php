<?php

namespace Drupal\mass_redirect_normalizer;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\mayflower\Helper;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Normalizes redirected internal links on content entities.
 */
class RedirectLinkNormalizationManager {
  private const REVISION_MESSAGE = 'Revision created to normalize redirected internal links.';
  private const NESTED_REVISION_MESSAGE = 'Revision created to normalize redirected internal links in nested content.';

  /**
   * Constructs the manager.
   */
  public function __construct(
    protected RedirectLinkResolver $resolver,
    protected TimeInterface $time,
  ) {
  }

  /**
   * Processes redirect-based links in an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Node or paragraph entity.
   * @param bool $save
   *   Whether to persist updates.
   *
   * @return array
   *   Processing result.
   */
  public function normalizeEntity(ContentEntityInterface $entity, bool $save = TRUE): array {
    if ($entity instanceof Paragraph && Helper::isParagraphOrphan($entity)) {
      return ['changed' => FALSE, 'skipped' => TRUE];
    }

    $changed = FALSE;
    foreach ($entity->getFields() as $field) {
      $fieldType = $field->getFieldDefinition()->getType();
      if (in_array($fieldType, ['text_long', 'text_with_summary', 'string_long'], TRUE)) {
        foreach ($field as $item) {
          $changed = $this->normalizeTextItem($item, $changed);
        }
      }
      elseif ($fieldType === 'link') {
        foreach ($field as $item) {
          $changed = $this->normalizeLinkItem($item, $changed);
        }
      }
    }

    if (!$changed || !$save) {
      return ['changed' => $changed, 'skipped' => FALSE];
    }

    $this->prepareRevision($entity, self::REVISION_MESSAGE);
    $entity->save();

    if ($entity->getEntityTypeId() === 'paragraph' && $node = Helper::getParentNode($entity)) {
      $this->prepareRevision($node, self::NESTED_REVISION_MESSAGE);
      $node->save();
    }

    return ['changed' => TRUE, 'skipped' => FALSE];
  }

  /**
   * Normalize a text item value and return updated changed flag.
   */
  private function normalizeTextItem(object $item, bool $changed): bool {
    if (!isset($item->value) || $item->value === NULL || $item->value === '') {
      return $changed;
    }

    $processed = $this->resolver->normalizeRedirectLinksInText($item->value);
    if ($processed['changed']) {
      $item->value = $processed['text'];
      return TRUE;
    }

    return $changed;
  }

  /**
   * Normalize a link item URI and return updated changed flag.
   */
  private function normalizeLinkItem(object $item, bool $changed): bool {
    if (empty($item->uri)) {
      return $changed;
    }

    $processed = $this->resolver->normalizeRedirectLinkUri($item->uri);
    if ($processed['changed']) {
      $item->uri = $processed['uri'];
      return TRUE;
    }

    return $changed;
  }

  /**
   * Configure revision metadata when supported by entity type.
   */
  private function prepareRevision(ContentEntityInterface $entity, string $message): void {
    if ($entity instanceof RevisionableInterface) {
      $entity->setNewRevision();
    }
    if ($entity instanceof RevisionLogInterface) {
      $entity->setRevisionLogMessage($message);
      $entity->setRevisionCreationTime($this->time->getRequestTime());
    }
  }

}
