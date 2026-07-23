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

}
