<?php

namespace Drupal\mass_schema_news_article\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaImageBase;

/**
 * Provides a plugin for the 'schema_news_article_image' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_news_article_image",
 *   label = @Translation("image"),
 *   description = @Translation("An image of the item."),
 *   name = "image",
 *   group = "schema_news_article",
 *   weight = 2,
 *   type = "image",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaNewsArticleImage extends SchemaImageBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
