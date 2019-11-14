<?php

namespace Drupal\mass_schema_news_article\Plugin\metatag\Group;

use Drupal\schema_metatag\Plugin\metatag\Group\SchemaGroupBase;

/**
 * Provides a plugin for the 'GovernmentService' meta tag group.
 *
 * @MetatagGroup(
 *   id = "schema_news_article",
 *   label = @Translation("Schema.org: NewsArticle"),
 *   description = @Translation("See Schema.org definitions for this Schema type at <a href="":url"">:url</a>.", arguments = { ":url" = "http://schema.org/NewsArticle"}),
 *   weight = 10,
 * )
 */
class SchemaNewsArticle extends SchemaGroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
