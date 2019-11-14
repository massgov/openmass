<?php

namespace Drupal\mass_content\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Lists all the referencing organizations from field_organizations.
 */
class ReferencedOrganizations extends EntityReferenceFieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  public function computeValue() {
    $entity = $this->getEntity();
    if (!$entity->isNew()) {
      $i = 0;

      // Create the entity reference values from field_organizations.
      if ($entity->hasField('field_organizations')) {
        // If the node is an 'org_page', add itself as a reference.
        if ($entity->getType() === 'org_page') {
          $this->list[$i] = $this->createItem($i, ['target_id' => $entity->id()]);
          $i++;
        }

        // Iterate through field_organizations to get the referenced orgs.
        $field = $entity->get('field_organizations');
        $organization_ids = array_column($field->getValue(), 'target_id');
        foreach ($organization_ids as $id) {
          $this->list[$i] = $this->createItem($i, ['target_id' => $id]);
          $i++;
        }
      }
    }
  }

}
