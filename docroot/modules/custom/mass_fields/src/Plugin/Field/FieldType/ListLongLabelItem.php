<?php

namespace Drupal\mass_fields\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\options\Plugin\Field\FieldType\ListStringItem;

/**
 * Plugin implementation of the 'list_long_label' field type.
 *
 * This field type extends the standard list field to support longer labels
 * and descriptions for each option, useful for accessibility attestations
 * and other use cases requiring detailed option descriptions.
 */
#[FieldType(
  id: "list_long_label",
  label: new TranslatableMarkup("List (with long labels)"),
  description: [
    new TranslatableMarkup("Values stored are text values with support for long labels and descriptions"),
    new TranslatableMarkup("Ideal for fields requiring detailed option descriptions, such as accessibility attestations"),
  ],
  category: "selection_list",
  weight: -45,
  default_widget: "list_long_label",
  default_formatter: "list_default",
)]
class ListLongLabelItem extends ListStringItem {

  /**
   * {@inheritdoc}
   */
  protected function allowedValuesDescription() {
    $description = '<p>' . $this->t('The label can contain formatted text and HTML.');
    $description .= '<br/>' . $this->t('The key is automatically generated as a machine name from the label and will be the stored value.');
    $description .= '</p>';
    return $description;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    // Get the parent form with machine name functionality.
    $element = parent::storageSettingsForm($form, $form_state, $has_data);

    // Only change: replace textfield with textarea for long HTML labels.
    foreach (Element::children($element['allowed_values']['table']) as $delta => $row) {
      if (isset($element['allowed_values']['table'][$delta]['item']['label'])) {
        $element['allowed_values']['table'][$delta]['item']['label']['#type'] = 'textarea';
        $element['allowed_values']['table'][$delta]['item']['label']['#rows'] = 5;
        $element['allowed_values']['table'][$delta]['item']['label']['#title'] = $this->t('Label (HTML allowed)');
        $element['allowed_values']['table'][$delta]['item']['label']['#description'] = $this->t('Enter the option label. HTML tags allowed: &lt;strong&gt;, &lt;em&gt;, &lt;p&gt;, &lt;br&gt;, &lt;a&gt;, &lt;ul&gt;, &lt;ol&gt;, &lt;li&gt;, &lt;span&gt;, &lt;small&gt;.');
      }
    }

    return $element;
  }

}
