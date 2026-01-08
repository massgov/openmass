<?php

namespace Drupal\mass_fields\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsButtonsWidget;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsWidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Component\Render\FormattableMarkup;

/**
 * Plugin implementation of the 'list_long_label_document_self_reported_accessibility_status_widget' widget.
 *
 * This widget extends the standard options widget to display radio buttons
 * for Self Reported Accessibility check.
 */
#[FieldWidget(
  id: 'list_long_label_document_self_reported_accessibility_status_widget',
  label: new TranslatableMarkup('Self Reported Accessibility Status Widget'),
  field_types: [
    'list_long_label',
  ],
  multiple_values: TRUE,
)]
class ListLongLabelDocumentSelfReportedAccessibilityStatusWidget extends ListLongLabelWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $options = $this->getOptions($items->getEntity());
    $selected = $this->getSelectedOptions($items);

    // If required and there is one single option, preselect it.
    if ($this->required && count($options) == 1) {
      $selected = [array_key_first($options)];
    }

    // Move "_none" item to the bottom.
    $none = array_shift($options);
    $options['_none'] = $none;

    $element['#type'] = 'radios';
    $element['#options'] = $options;
    $element['#default_value'] = $selected ? reset($selected) : '_none';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmptyLabel() {
    if (!$this->required && !$this->multiple) {
      return new FormattableMarkup('<strong>TBD:</strong> You can\'t publish this document unless you choose one of the other states.', []);
    }
  }

}
