<?php

namespace Drupal\mass_migrate\Plugin\Field\FieldType;

use Drupal\entity_hierarchy\Plugin\Field\FieldType\EntityReferenceHierarchy;

class EntityHierarchyOverriddenItem extends EntityReferenceHierarchy {

  public function postSave($update) {
    // Add the item to the queue to process later.
    \Drupal::queue('entity_hierarchy_tracker')->createItem([
      'field_item' => $this,
    ]);
  }

}
