<?php

namespace Drupal\mass_content\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * List both the Featured Services and More Services for an Org Landing Page.
 */
class OrgPageAllServices extends EntityReferenceFieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  public function computeValue() {
    $entity = $this->getEntity();
    if (!$entity->isNew()) {
      $i = 0;

      // Add all of the "featured services". This is a Link field so the value
      // is stored as the "uri" element of the array.
      foreach ($entity->field_links_actions_3->getValue() as $item) {
        // Make sure this item points to an internal entity instead of a remote
        // path.
        if (strpos($item['uri'], 'entity:') !== FALSE) {
          list($entity_type, $entity_id) = explode('/', str_replace('entity:', '', $item['uri']));
          // I'm not sure why the entity type isn't needed? But it appears to
          // work fine as is.
          $this->list[$i] = $this->createItem($i, ['target_id' => $entity_id]);
          $i++;
        }
      }

      // Add all of the "more services". These are normal Entity Reference
      // values so the logic is simpler than above.
      foreach ($entity->field_ref_actions_6->getValue() as $item) {
        $this->list[$i] = $this->createItem($i, $item);
        $i++;
      }
    }
  }

}
