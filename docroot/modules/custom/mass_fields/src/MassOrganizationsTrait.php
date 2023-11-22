<?php

namespace Drupal\mass_fields;

use Drupal\Core\Field\FieldItemListInterface;

trait MassOrganizationsTrait {

  /**
   * Gets Organizations list.
   */
  public function getOrganizations(): ?FieldItemListInterface {
    if ($this->hasField('field_organizations')) {
      return $this->get('field_organizations');
    }
  }

}
