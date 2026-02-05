<?php

namespace Drupal\mass_schema_web_page\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_web_page_is_based_on' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_web_page_is_based_on",
 *   label = @Translation("isBasedOn"),
 *   description = @Translation("A resource that was used in the creation of this resource. This term can be repeated for multiple sources. For example, http://example.com/great-multiplication-intro.html."),
 *   name = "isBasedOn",
 *   group = "schema_web_page",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaWebPageIsBasedOn extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []): array {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_decision_sources]';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function output(): array {
    $element = parent::output();

    $links = json_decode($this->value(), TRUE);

    if (!empty($element) && is_array($links)) {
      $element['#attributes']['content'] = [];

      // Iterate through each link to get the url.
      foreach ($links as $link) {
        if (empty($link['url'])) {
          continue;
        }
        $element['#attributes']['content'][] = $link['url'];
      }
    }
    return $element;
  }

}
