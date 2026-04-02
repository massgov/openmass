<?php

namespace Drupal\mass_schema_government_service\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;
use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Provides a plugin for the 'areaServed' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_government_service_area_served",
 *   label = @Translation("Area Served"),
 *   description = @Translation("The geographic area where a service or offered item is provided. Supersedes serviceArea."),
 *   name = "areaServed",
 *   group = "schema_government_service",
 *   weight = 6,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaGovernmentServiceAreaServed extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   *
   * We need multiple values, so create a tree of values and
   * stored the serialized value as a string.
   */
  public function form(array $element = []): array {
    $value = SchemaMetatagManager::unserialize($this->value());
    $form['#type'] = 'details';
    $form['#description'] = $this->description();
    $form['#open'] = !empty($value['name']);
    $form['#tree'] = TRUE;
    $form['#title'] = $this->label();
    $form['@type'] = [
      '#type' => 'select',
      '#title' => $this->t('@type'),
      '#default_value' => !empty($value['@type']) ? $value['@type'] : '',
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => [
        'AdministrativeArea' => $this->t('AdministrativeArea'),
        'GeoShape' => $this->t('GeoShape'),
        'Place' => $this->t('Place'),
        'Text' => $this->t('Text'),
      ],
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('name'),
      '#default_value' => !empty($value['name']) ? $value['name'] : '',
      '#maxlength' => 255,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      '#description' => $this->t("The name of the area served."),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function output(): array {
    $element = parent::output();
    if (!empty($element)) {
      $content = SchemaMetatagManager::unserialize($this->value());
      // If there is no value, don't create a tag.
      $keys = ['@type', 'name'];
      $empty = TRUE;
      foreach ($keys as $key) {
        if (!empty($content[$key])) {
          $empty = FALSE;
          break;
        }
      }
      if ($empty) {
        return '';
      }
      $element['#attributes']['group'] = $this->group;
      $element['#attributes']['schema_metatag'] = TRUE;
      $element['#attributes']['content'] = [];
      foreach ($keys as $key) {
        if (!empty($content[$key])) {
          $value = $content[$key];
          $element['#attributes']['content'][$key] = $value;
        }
      }
    }
    return $element;
  }

}
