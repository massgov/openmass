<?php

namespace Drupal\mass_inline_message\Form;

/**
 * Applies Maxlength module attributes to dialog form elements.
 *
 * Mirrors maxlength_field_widget_single_element_form_alter() for custom forms.
 */
final class MaxlengthFormHelper {

  /**
   * Applies maxlength.js to a textfield, textarea, or text_format element.
   *
   * @param array $element
   *   Form element to alter.
   * @param int $limit
   *   Maximum plain-text character count.
   * @param array $options
   *   Options with keys: enforce (bool), label (string|\Stringable).
   */
  public static function apply(array &$element, int $limit, array $options = []): void {
    $enforce = $options['enforce'] ?? TRUE;
    $label = $options['label'] ?? t('Content limited to @limit characters, remaining: <strong>@remaining</strong>');

    $element['#maxlength_js'] = TRUE;
    $element['#attributes']['class'][] = 'maxlength';
    $element['#attached']['library'][] = 'maxlength/maxlength';
    $element['#attributes']['data-maxlength'] = $limit;
    $element['#attributes']['maxlength_js_label'][] = $label;

    if ($enforce) {
      $element['#attributes']['class'][] = 'maxlength_js_enforce';
      $element['#attributes']['#maxlength_js_enforce'] = TRUE;
    }

    if (isset($element['value'])) {
      $element['value']['#maxlength_js'] = TRUE;
      $element['value']['#attributes']['class'][] = 'maxlength';
      $element['value']['#attributes']['data-maxlength'] = $limit;
      $element['value']['#attributes']['maxlength_js_label'][] = $label;
      if ($enforce) {
        $element['value']['#attributes']['class'][] = 'maxlength_js_enforce';
        $element['value']['#attributes']['#maxlength_js_enforce'] = TRUE;
      }
    }
  }

}
