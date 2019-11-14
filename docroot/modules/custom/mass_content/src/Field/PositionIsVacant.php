<?php

namespace Drupal\mass_content\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Check if the board member position is vacant.
 */
class PositionIsVacant extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  public function computeValue() {
    $entity = $this->getParent()->getValue();
    // Check if the entity is referenced on a paragraph where the 'Vacant'
    // boolean field is true.
    if (!empty($entity->_referringItem)) {
      $board_member_paragraph = $entity->_referringItem->getEntity();
      if ($board_member_paragraph->field_position_is_vacant->value) {
        $this->list[0] = $this->createItem(0, TRUE);
      }
      else {
        $this->list[0] = $this->createItem(0, FALSE);
      }
    }
  }

}
