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
 * Plugin implementation of the 'list_long_label' widget.
 *
 * This widget extends the standard options widget to display radio buttons
 * with long labels and descriptions, ideal for accessibility attestations.
 */
#[FieldWidget(
  id: 'list_long_label',
  label: new TranslatableMarkup('Checkboxes/Radio buttons with long labels'),
  field_types: [
    'list_long_label',
  ],
  multiple_values: TRUE,
)]

class ListLongLabelWidget extends OptionsButtonsWidget {

  /**
   * Prepares options by ensuring labels are rendered as formatted HTML.
   *
   * @param array $options
   *   The options array.
   *
   * @return array
   *   Processed options array with HTML labels.
   */
  protected function prepareOptions(array $options) {
    $processed = [];

    // Define allowed HTML tags for labels.
    $allowed_tags = ['strong', 'em', 'p', 'br', 'a', 'ul', 'ol', 'li', 'span', 'small'];

    foreach ($options as $key => $label) {
      // Filter HTML to only allow specific tags, then mark as safe.
      $filtered_label = Xss::filter($label, $allowed_tags);
      $processed[$key] = new FormattableMarkup($filtered_label, []);
    }

    return $processed;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmptyLabel() {
    if (!$this->required && !$this->multiple) {
      return $this->t('N/A');
    }

    return NULL;
  }

}
