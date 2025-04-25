<?php

namespace Drupal\mass_fields\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\key_value_field\Plugin\Field\FieldWidget\KeyValueTextareaWidget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'key_value' widget.
 *
 * Make the value field look like a single simple textarea, so later we can add
 * WYSIWYG if requested.
 *
 * @FieldWidget(
 *   id = "mass_glossary_key_value_textarea",
 *   label = @Translation("Mass Glossary Key / Value (multiple rows)"),
 *   field_types = {
 *     "key_value_long"
 *   }
 * )
 */
class MassGlossaryKeyValueTextareaWidget extends KeyValueTextareaWidget {

  /**
   * @inheritDoc
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $build = parent::formElement($items, $delta, $element, $form, $form_state);
    $build["#allowed_formats"] = ['plain_text'];
    $build['#after_build'][] = [$this, 'hideHelpTextAfterBuild'];;
    return $build;
  }

  /**
   * Removes unnecessary help text and formatting wrappers from the given element
   * after the form has been built.
   *
   * @param array $element
   *   The form element to be modified.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The modified form element with specific help text and wrappers removed.
   */
  public function hideHelpTextAfterBuild(array $element, FormStateInterface $form_state) {
    unset($element['format']['#type']);
    unset($element['format']['#theme_wrappers']);
    unset($element['format']['help']);
    unset($element['format']['guidelines']);
    return $element;
  }

}
