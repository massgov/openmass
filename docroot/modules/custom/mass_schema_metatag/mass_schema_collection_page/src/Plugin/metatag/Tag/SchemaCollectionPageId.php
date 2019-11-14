<?php

namespace Drupal\mass_schema_collection_page\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_collection_page_id' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_collection_page_id",
 *   label = @Translation("@id"),
 *   description = @Translation("The ID of collection page."),
 *   name = "@id",
 *   group = "schema_collection_page",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaCollectionPageId extends SchemaNameBase {

}
