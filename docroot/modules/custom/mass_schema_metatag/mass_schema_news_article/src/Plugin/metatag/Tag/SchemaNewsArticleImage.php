<?php

namespace Drupal\mass_schema_news_article\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaImageObjectBase;

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
 *   multiple = FALSE,
 *   property_type = "image_object",
 *   tree_parent = {
 *     "ImageObject",
 *   },
 *   tree_depth = 0
 * )
 */
class SchemaNewsArticleImage extends SchemaImageObjectBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
