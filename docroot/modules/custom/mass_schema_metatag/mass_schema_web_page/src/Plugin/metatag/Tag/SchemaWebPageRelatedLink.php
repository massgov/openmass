<?php

namespace Drupal\mass_schema_web_page\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for 'schema_web_page_related_link' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_web_page_related_link",
 *   label = @Translation("relatedLink"),
 *   description = @Translation("A related link."),
 *   name = "relatedLink",
 *   group = "schema_web_page",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaWebPageRelatedLink extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []): array
  {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_decision_related]';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function output(): array
  {
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
