<?php

namespace Drupal\mass_schema_news_article\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_news_article_id' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_news_article_id",
 *   label = @Translation("@id"),
 *   description = @Translation("The ID of the news article."),
 *   name = "@id",
 *   group = "schema_news_article",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaNewsArticleId extends SchemaNameBase {

}
