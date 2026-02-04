<?php

namespace Drupal\mass_schema_web_page\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_web_page_about' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_web_page_about",
 *   label = @Translation("about"),
 *   description = @Translation("The subject matter of the content."),
 *   name = "about",
 *   group = "schema_web_page",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaWebPageAbout extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []): array
  {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_decision_overview]';
    return $form;
  }

}
