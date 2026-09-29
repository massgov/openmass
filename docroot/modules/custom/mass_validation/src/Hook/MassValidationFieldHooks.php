<?php

namespace Drupal\mass_validation\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Field constraint hook implementations for mass_validation.
 */
class MassValidationFieldHooks {

  /**
   * Rejects attaching the same SME / content owner twice to one entity.
   *
   * The widget offers a row per value and core does not object to a repeat,
   * which leaves the name doubled in every report that lists it. Applied
   * wherever the field exists, so documents are covered alongside pages.
   */
  #[Hook('entity_bundle_field_info_alter')]
  public function addDuplicateOwnerConstraint(array &$fields, EntityTypeInterface $entity_type, string $bundle): void {
    if (array_key_exists('field_sme_content_owner', $fields) && !empty($fields['field_sme_content_owner'])) {
      $fields['field_sme_content_owner']->addConstraint('MassDuplicateReference');
    }
  }

}
