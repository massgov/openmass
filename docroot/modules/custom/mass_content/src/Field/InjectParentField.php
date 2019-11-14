<?php

namespace Drupal\mass_content\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Add a parent field to a paragraph display.
 */
class InjectParentField extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  public function computeValue() {
    $entity = $this->getEntity();
    if (!$entity->isNew()) {
      $node = $entity->getParentEntity();
      if (!empty($node) && !$node->isNew()) {
        // Because this could be a revision, get the node from the request.
        $node = \Drupal::request()->attributes->get('node');
        $fields = $this->getSetting('parent_field');
        foreach ($fields as $field) {
          if (!empty($node->{$field})) {
            foreach ($node->{$field} as $item) {
              $this->list[] = $item;
            }
            break;
          }
        }
      }
    }
  }

}
