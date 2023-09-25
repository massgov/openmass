<?php

namespace Drupal\mass_fields;

use Drupal\Core\Field\FieldItemListInterface;

trait MassCollectionTrait {

  /**
   * Get search value.
   */
  public function getCollection(): ?FieldItemListInterface {
    return $this->hasField('field_collections') ? $this->get('field_collections') : NULL;
  }

}
