<?php

namespace Drupal\mass_schema_collection_page\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaImageBase;
use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Provides a plugin for 'schema_collection_page_primary_image_of_page' metatag.
 *
 * @MetatagTag(
 *   id = "schema_collection_page_primary_image_of_page",
 *   label = @Translation("primaryImageOfPage"),
 *   description = @Translation("Indicates the main image on the page."),
 *   name = "primaryImageOfPage",
 *   group = "schema_collection_page",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaCollectionPagePrimaryImageOfPage extends SchemaImageBase {

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
      '@type' => 'ImageObject',
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

        $element['#attributes']['content'][] = [
          '@type' => 'ImageObject',
          'url' => $url,
        ];
      }
    }

    return $element;
  }

}
