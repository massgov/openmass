<?php

namespace Drupal\mass_redirect_normalizer;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\mayflower\Helper;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Normalizes redirected internal links on content entities.
 */
class RedirectLinkNormalizationManager {

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
          if (!isset($item->value) || $item->value === NULL || $item->value === '') {
            continue;
          }
          $processed = $this->resolver->normalizeRedirectLinksInText($item->value);
          if ($processed['changed']) {
            $item->value = $processed['text'];
            $changed = TRUE;
          }
        }
      }
      elseif ($fieldType === 'link') {
        foreach ($field as $item) {
          if (empty($item->uri)) {
            continue;
          }
          $processed = $this->resolver->normalizeRedirectLinkUri($item->uri);
          if ($processed['changed']) {
            $item->uri = $processed['uri'];
            $changed = TRUE;
          }
        }
      }
    }

    if (!$changed || !$save) {
      return ['changed' => $changed, 'skipped' => FALSE];
    }

    if (method_exists($entity, 'setNewRevision')) {
      call_user_func([$entity, 'setNewRevision']);
    }
    if (method_exists($entity, 'setRevisionLogMessage')) {
      call_user_func([$entity, 'setRevisionLogMessage'], 'Revision created to normalize redirected internal links.');
    }
    if (method_exists($entity, 'setRevisionCreationTime')) {
      call_user_func([$entity, 'setRevisionCreationTime'], $this->time->getRequestTime());
    }
    $entity->save();

    if ($entity->getEntityTypeId() === 'paragraph' && $node = Helper::getParentNode($entity)) {
      if (method_exists($node, 'setNewRevision')) {
        $node->setNewRevision();
      }
      if (method_exists($node, 'setRevisionLogMessage')) {
        $node->setRevisionLogMessage('Revision created to normalize redirected internal links in nested content.');
      }
      if (method_exists($node, 'setRevisionCreationTime')) {
        $node->setRevisionCreationTime($this->time->getRequestTime());
      }
      $node->save();
    }

    return ['changed' => TRUE, 'skipped' => FALSE];
  }

}

