<?php

namespace Drupal\mass_validation\Validation;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Validates that the Map field only references location content types.
 *
 * This guards the form-submission path, where a pasted node ID can otherwise
 * bypass the autocomplete widget's location-only selection handler. Non-form
 * saves are covered by Drupal core's ValidReferenceConstraint, which validates
 * against the field's views selection handler.
 */
class LocationReferenceValidator {

  use StringTranslationTrait;

  /**
   * The Map field machine name.
   */
  public const FIELD_NAME = 'field_org_ref_locations';

  /**
   * The only allowed node bundle for Map field references.
   */
  public const ALLOWED_BUNDLE = 'location';

  /**
   * LocationReferenceValidator constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Sets a form error for each non-location reference in a value array.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $locations
   *   The field_org_ref_locations form values.
   * @param string $error_prefix
   *   The form error name prefix (without trailing bracket).
   */
  public function validateFormValues(FormStateInterface $form_state, array $locations, string $error_prefix): void {
    $storage = $this->entityTypeManager->getStorage('node');
    foreach ($locations as $delta => $location) {
      if (!is_array($location) || empty($location['target_id'])) {
        continue;
      }

      $node = $storage->load($location['target_id']);
      if (!$node instanceof NodeInterface || $node->bundle() === self::ALLOWED_BUNDLE) {
        continue;
      }

      $form_state->setErrorByName(
        $error_prefix . '][' . $delta . '][target_id',
        $this->t('Only location pages can be referenced in this field. The item "@title" is a @type page and is not allowed.', [
          '@title' => $node->getTitle(),
          '@type' => $node->bundle(),
        ])
      );
    }
  }

  /**
   * Validates Map field values within a nested paragraph subform.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $paragraph
   *   The paragraph subform values.
   * @param string $error_prefix
   *   The form error name prefix for the paragraph subform.
   */
  public function validateParagraphSubform(FormStateInterface $form_state, array $paragraph, string $error_prefix): void {
    if (!isset($paragraph['subform'][self::FIELD_NAME])) {
      return;
    }

    $this->validateFormValues(
      $form_state,
      $paragraph['subform'][self::FIELD_NAME],
      $error_prefix . '][' . self::FIELD_NAME
    );
  }

  /**
   * Validates Map field values on layout paragraph component forms.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateLayoutParagraphForm(FormStateInterface $form_state): void {
    if (!$form_state->hasValue(self::FIELD_NAME)) {
      return;
    }

    $this->validateFormValues(
      $form_state,
      $form_state->getValue(self::FIELD_NAME),
      self::FIELD_NAME
    );
  }

}
