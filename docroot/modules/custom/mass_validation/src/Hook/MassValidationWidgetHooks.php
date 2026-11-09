<?php

namespace Drupal\mass_validation\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for mass_validation.
 */
class MassValidationWidgetHooks {

  /**
   * Re-enables pasted-ID validation against the Map field's Views handler.
   */
  #[Hook('field_widget_single_element_entity_reference_autocomplete_form_alter')]
  public function mapFieldValidateReference(array &$element, FormStateInterface $form_state, array $context): void {
    if ($context['items']->getFieldDefinition()->getName() === 'field_org_ref_locations') {
      $element['target_id']['#validate_reference'] = TRUE;
    }
  }

  /**
   * Lets owner names end in brackets without being read as an entity ID.
   *
   * @see \Drupal\mass_validation\Element\OwnerAutocomplete
   */
  #[Hook('field_widget_single_element_entity_reference_autocomplete_form_alter')]
  public function ownerAutocompleteParsing(array &$element, FormStateInterface $form_state, array $context): void {
    if ($context['items']->getFieldDefinition()->getName() === 'field_sme_content_owner') {
      $element['target_id']['#type'] = 'mass_owner_autocomplete';
    }
  }

}
