<?php

namespace Drupal\mass_schema_news_article\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'datePublished' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_news_article_date_published",
 *   label = @Translation("datePublished"),
 *   description = @Translation("Date the article was published."),
 *   name = "datePublished",
 *   group = "schema_news_article",
 *   weight = 3,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "date",
 *   tree_parent = {},
 *   tree_depth = 0
 * )
 */
class SchemaNewsArticleDatePublished extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:created:html_datetime]';
    return $form;
  }

}
