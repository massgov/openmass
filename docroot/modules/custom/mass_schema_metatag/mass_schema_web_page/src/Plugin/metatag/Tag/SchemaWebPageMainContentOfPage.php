<?php

namespace Drupal\mass_schema_web_page\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_web_page_main_content_of_page' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_web_page_main_content_of_page",
 *   label = @Translation("mainContentOfPage"),
 *   description = @Translation("Indicates if this web page element is the main subject of the page."),
 *   name = "mainContentOfPage",
 *   group = "schema_web_page",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaWebPageMainContentOfPage extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []): array
  {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:title]';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function output(): array
  {
    $element = parent::output();
    $value = json_decode($this->value(), TRUE);
    if (!empty($element)) {
      $element['#attributes']['content'] = [];
      $element['#attributes']['content'][] = [
        '@type' => 'WebPageElement',
        'text' => $value,
      ];
    }
    return $element;
  }

}
