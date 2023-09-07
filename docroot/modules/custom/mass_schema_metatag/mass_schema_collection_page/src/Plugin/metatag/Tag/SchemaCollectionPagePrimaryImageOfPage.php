<?php

namespace Drupal\mass_schema_collection_page\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaImageObjectBase;

/**
 * Provides a plugin for 'schema_collection_page_primary_image_of_page' metatag.
 *
 * @MetatagTag(
 *   id = "schema_collection_page_primary_image_of_page",
 *   label = @Translation("primaryImageOfPage"),
 *   description = @Translation("Indicates the main image on the page."),
 *   name = "primaryImageOfPage",
 *   group = "schema_collection_page",
 *   weight = 1,
 *   type = "image",
 *   secure = FALSE,
 *   multiple = TRUE,
 *   property_type = "image_object",
 *   tree_parent = {
 *     "ImageObject",
 *   },
 *   tree_depth = 0
 * )
 */
class SchemaCollectionPagePrimaryImageOfPage extends SchemaImageObjectBase {

}
