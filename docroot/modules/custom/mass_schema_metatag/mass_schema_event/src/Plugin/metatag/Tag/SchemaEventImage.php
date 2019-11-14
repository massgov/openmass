<?php

namespace Drupal\mass_schema_event\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaImageBase;
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
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaEventImage extends SchemaImageBase {

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
    $element = parent::output();

    $element['#attributes']['content'] = [];

    $images = SchemaMetatagManager::unserialize($this->value());
    foreach ($images as $image) {
      // If it is null, continue;.
      if (empty($image)) {
        continue;
      }

      $url = json_decode($image, TRUE);
      $element['#attributes']['content'][] = [
        '@type' => 'ImageObject',
        'url' => $url,
      ];
    }

    return $element;
  }

}
