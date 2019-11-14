<?php

namespace Drupal\mass_schema_collection_page\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaImageBase;

/**
 * Provides a plugin for the 'logo' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_collection_page_logo",
 *   label = @Translation("logo"),
 *   description = @Translation("Logo or image of the Guide page. An image of the item. This can be a URL or a fully described ImageObject."),
 *   name = "logo",
 *   group = "schema_collection_page",
 *   weight = 6,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaCollectionPageLogo extends SchemaImageBase {

}
