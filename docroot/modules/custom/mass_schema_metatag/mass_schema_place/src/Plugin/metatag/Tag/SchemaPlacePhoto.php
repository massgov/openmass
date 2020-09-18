<?php

namespace Drupal\mass_schema_place\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaImageBase;
use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Provides a plugin for the 'schema_government_service_description' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_place_photo",
 *   label = @Translation("photo"),
 *   description = @Translation("The photo of the place."),
 *   name = "photo",
 *   group = "schema_place",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaPlacePhoto extends SchemaImageBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:summary]';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function output() {
    $value = SchemaMetatagManager::recomputeSerializedLength($this->value());
    $value = unserialize($value);
    $value = array_merge($value, [
      '@type' => 'Photograph',
    ]);
    $this->setValue(SchemaMetatagManager::serialize($value));

    $element = parent::output();

    if (!empty($element)) {
      $element['#attributes']['content'] = [];
      $images = SchemaMetatagManager::unserialize($this->value());

      if (empty($images['url'])) {
        return '';
      }

      $images = explode(', ', $images['url']);

      foreach ($images as $url) {
        // If it is null, continue;.
        if (empty($url)) {
          continue;
        }

        $element['#attributes']['content'][] = json_decode($url, TRUE);
      }
    }

    return $element;
  }

}
