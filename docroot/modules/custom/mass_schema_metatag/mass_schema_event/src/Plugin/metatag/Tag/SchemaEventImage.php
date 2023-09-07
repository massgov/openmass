<?php

namespace Drupal\mass_schema_event\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaImageObjectBase;
use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;
use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Provides a plugin for 'schema_event_image' metatag.
 *
 * @MetatagTag(
 *   id = "schema_event_image",
 *   label = @Translation("image"),
 *   description = @Translation("Indicates the main image on the page."),
 *   name = "image",
 *   group = "schema_event",
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
class SchemaEventImage extends SchemaImageObjectBase {

}
