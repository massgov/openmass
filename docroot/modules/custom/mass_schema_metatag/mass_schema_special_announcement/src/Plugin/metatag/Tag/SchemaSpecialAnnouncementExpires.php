<?php

namespace Drupal\mass_schema_special_announcement\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'Expires' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_special_announcement_expires",
 *   label = @Translation("Expires"),
 *   description = @Translation("When the announcement expires."),
 *   name = "expires",
 *   group = "schema_special_announcement",
 *   weight = 4,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "date",
 *   tree_parent = {},
 *   tree_depth = 0
 * )
 */
class SchemaSpecialAnnouncementExpires extends SchemaNameBase {

}
