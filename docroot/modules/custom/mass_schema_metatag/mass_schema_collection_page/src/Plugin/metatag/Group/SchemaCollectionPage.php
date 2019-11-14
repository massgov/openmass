<?php

namespace Drupal\mass_schema_collection_page\Plugin\metatag\Group;

use Drupal\schema_metatag\Plugin\metatag\Group\SchemaGroupBase;

/**
 * Provides a plugin for the 'CollectionPage' meta tag group.
 *
 * @MetatagGroup(
 *   id = "schema_collection_page",
 *   label = @Translation("Schema.org: Collection Page"),
 *   description = @Translation("See Schema.org definitions for this Schema type at <a href="":url"">:url</a>.", arguments = { ":url" = "http://schema.org/CollectionPage"}),
 *   weight = 10,
 * )
 */
class SchemaCollectionPage extends SchemaGroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
