<?php

namespace Drupal\mass_schema_web_page\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_web_page_content_location' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_web_page_content_location",
 *   label = @Translation("contentLocation"),
 *   description = @Translation("The location depicted or described in the content. For example, the location in a photograph or painting."),
 *   name = "contentLocation",
 *   group = "schema_web_page",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaWebPageContentLocation extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []): array
  {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_decision_location]';
    return $form;
  }

}
