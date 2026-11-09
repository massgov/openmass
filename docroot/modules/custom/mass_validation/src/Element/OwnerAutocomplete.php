<?php

namespace Drupal\mass_validation\Element;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;

/**
 * Entity autocomplete for SME / content owners, whose names end in brackets.
 *
 * Owner names follow the email convention, "Jane Doe (DPH)". Core reads the
 * last bracketed part of any autocomplete input as the entity ID, so a typed
 * new name came back as "the term with ID DPH" and failed instead of being
 * created. Here only a number in the final brackets counts as an ID, which is
 * what a picked suggestion looks like: "Jane Doe (DPH) (123)", optionally
 * followed by the vocabulary name the project's matcher appends.
 */
#[FormElement('mass_owner_autocomplete')]
class OwnerAutocomplete extends EntityAutocomplete {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    array_unshift($info['#element_validate'], [static::class, 'normalizeOwnerInput']);
    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function extractEntityIdFromAutocompleteInput($input) {
    // The ID may be followed by the " - Vocabulary name" suffix that
    // \Drupal\mass_fields\EntityAutocompleteMatcher adds to suggestions, and
    // by the closing quote Tags::encode() adds to names with commas.
    return preg_match('/\((\d+)\)(?:\s+-\s+[^()]*)?["\s]*$/u', (string) $input, $matches) ? $matches[1] : NULL;
  }

  /**
   * Normalizes spacing so variants of one name do not become separate owners.
   *
   * "Jane  Doe(DPH)" and "Jane Doe (DPH)" are the same person, and the
   * vocabulary's uniqueness check compares names literally.
   */
  public static function normalizeOwnerInput(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    if (!is_string($element['#value']) || $element['#value'] === '') {
      return;
    }
    $value = preg_replace('/\s+/u', ' ', trim($element['#value']));
    $value = preg_replace('/\s*\(\s*([^()]*?)\s*\)/u', ' ($1)', $value);
    $element['#value'] = trim($value);
    $form_state->setValueForElement($element, $element['#value']);
  }

}
