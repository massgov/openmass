<?php

namespace Drupal\mass_schema_news_article\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides plugin for 'schema_news_article_disambiguating_description' metatag.
 *
 * @MetatagTag(
 *   id = "schema_news_article_disambiguating_description",
 *   label = @Translation("disambiguatingDescription"),
 *   description = @Translation("A sub property of description. A short description of the item used to disambiguate from other, similar items. Information from other properties (in particular, name) may be necessary for the description to be useful for disambiguation."),
 *   name = "disambiguatingDescription",
 *   group = "schema_news_article",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaNewsArticleDisambiguatingDescription extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []): array
  {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:summary]';
    return $form;
  }

}
