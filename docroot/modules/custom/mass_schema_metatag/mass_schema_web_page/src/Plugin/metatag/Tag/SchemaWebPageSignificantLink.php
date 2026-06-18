<?php

namespace Drupal\mass_schema_web_page\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_web_page_significant_link' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_web_page_significant_link",
 *   label = @Translation("significantLink"),
 *   description = @Translation("One of the more significant URLs on the page. Typically, these are the non-navigation links that are clicked on the most."),
 *   name = "significantLink",
 *   group = "schema_web_page",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaWebPageSignificantLink extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []): array {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:title]';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function output(): array {
    $element = parent::output();
    if (!empty($element)) {
      $element['#attributes']['content'] = [];

      $values = json_decode($this->value(), TRUE);
      if (!empty($values)) {
        $element['#attributes']['content'] = $values;
      }
    }
    return $element;
  }

}
