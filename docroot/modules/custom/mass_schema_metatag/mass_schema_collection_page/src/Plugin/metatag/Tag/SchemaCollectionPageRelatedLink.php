<?php

namespace Drupal\mass_schema_collection_page\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for 'schema_collection_page_related_link' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_collection_page_related_link",
 *   label = @Translation("relatedLink"),
 *   description = @Translation("A related link."),
 *   name = "relatedLink",
 *   group = "schema_collection_page",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaCollectionPageRelatedLink extends SchemaNameBase {

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
