<?php

namespace Drupal\mass_schema_event\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaImageObjectBase;
use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Provides a plugin for 'schema_event_image' metatag.
 *
 * @MetatagTag(
 *   id = "schema_event_image",
 *   label = @Translation("image"),
 *   description = @Translation("Indicates the main image on the page."),
 *   name = "image",
 *   group = "schema_event",
 *   weight = 1,
 *   type = "image",
 *   secure = FALSE,
 *   multiple = TRUE,
 *   property_type = "image_object",
 *   tree_parent = {
 *     "ImageObject",
 *   },
 *   tree_depth = 0
 * )
 */
class SchemaEventImage extends SchemaImageObjectBase {

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
  public function output(): array {
    $value = SchemaMetatagManager::recomputeSerializedLength($this->value());
    $value = unserialize($value);
    $value = array_merge($value, [
      '@type' => 'ImageObject',
    ]);
    $this->setValue(SchemaMetatagManager::serialize($value));

    $element = parent::output();

    if (!empty($element)) {
      $element['#attributes']['content'] = [];
      $images = SchemaMetatagManager::unserialize($this->value());

      if (empty($images['url'])) {
        return [];
      }

      $images = explode(', ', $images['url']);

      foreach ($images as $url) {
        // If it is null, continue;.
        if (empty($url)) {
          continue;
        }

        $element['#attributes']['content'][] = [
          '@type' => 'ImageObject',
          'url' => json_decode($url, TRUE),
        ];
      }
    }

    return $element;
  }

}
