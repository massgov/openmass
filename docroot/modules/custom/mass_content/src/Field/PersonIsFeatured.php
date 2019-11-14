<?php

namespace Drupal\mass_content\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Add a parent field to a paragraph display.
 */
class PersonIsFeatured extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  public function computeValue() {
    $entity = $this->getParent()->getValue();
    // If this person node is rendered in the preview card mode and is
    // referenced from another field, check if it is a featured member.
    if (!empty($entity->_referringItem)) {
      $board_member_paragraph = $entity->_referringItem->getEntity();
      $board_member_paragraph_id = $board_member_paragraph->id();
      $listing_paragraph = $board_member_paragraph->getParentEntity();

      // If it is a featured board member, set the flag.
      if ($listing_paragraph->hasField('field_featured_board_members') && !empty($listing_paragraph->field_featured_board_members->getValue())) {
        $target_values = $listing_paragraph->field_featured_board_members->getValue();
        $this->list[0] = $this->createItem(0, FALSE);
        foreach ($target_values as $value) {
          if ($value['target_id'] == $board_member_paragraph_id) {
            $this->list[0] = $this->createItem(0, TRUE);
            break;
          }
        }
      }
    }
  }

}
