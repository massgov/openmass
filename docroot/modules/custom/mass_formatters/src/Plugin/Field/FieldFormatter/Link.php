<?php

namespace Drupal\mass_formatters\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;

/**
 * Plugin implementation of the 'class_link' formatter.
 *
 * @FieldFormatter(
 *   id = "mass_link",
 *   label = @Translation("Link with class"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class Link extends LinkFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    $classes = $this->getSetting('classes');

    foreach ($elements as &$element) {
      $options = $element['#options'];
      $attributes = [];

      if (array_key_exists('attributes', $options)) {
        $attributes = $options['attributes'];
      }

      $element['#options']['attributes'] = $attributes + ['class' => $classes];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'classes' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['classes'] = [
      '#title' => t('Classes'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('classes'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = t('Additional classes: %classes', ['%classes' => $this->getSetting('classes')]);

    return $summary;
  }

}
