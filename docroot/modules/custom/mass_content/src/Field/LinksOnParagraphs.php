<?php

namespace Drupal\mass_content\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Collect instances of a link on a paragraph.
 *
 * This utilizes the 'field' setting for passing in the field name that references the paragraph instances, a
 * 'paragraph_field' that references the link field to copy from the paragraph, and an optional limit field to specify
 * how many link instances should be returned.
 */
class LinksOnParagraphs extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  public function computeValue() {
    $entity = $this->getEntity();
    if (!$entity->isNew()) {
      $field = $this->getSetting('field');
      $paragraph_field = $this->getSetting('paragraph_field');
      $limit = $this->getSetting('limit');
      foreach ($entity->{$field}->referencedEntities() as $paragraph) {
        foreach ($paragraph->{$paragraph_field} as $link) {
          $this->list[] = $link;
          if (!empty($limit) && $this->count() >= $limit) {
            break 2;
          }
        }
      }
    }
  }

}
