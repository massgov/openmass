<?php

namespace Drupal\mass_schema_place\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_place_id' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_place_id",
 *   label = @Translation("@id"),
 *   description = @Translation("The ID of place."),
 *   name = "@id",
 *   group = "schema_place",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaPlaceId extends SchemaNameBase {

}
