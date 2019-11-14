<?php

namespace Drupal\mass_schema_collection_page\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_collection_page_main_entity' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_collection_page_main_entity",
 *   label = @Translation("mainEntity"),
 *   description = @Translation("Indicates the primary entity described in some page or other CreativeWork."),
 *   name = "mainEntity",
 *   group = "schema_collection_page",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaCollectionPageMainEntity extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_guide_page_related_guides]';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value) {
    $this->value = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function output() {
    $element = parent::output();

    // Get the links.
    $links = json_decode($this->value(), TRUE);

    // Assign the links array to the element for output.
    if (!empty($element) && is_array($links)) {
      $element['#attributes']['content'] = $links;
    }

    return $element;
  }

}
