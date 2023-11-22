<?php

namespace Drupal\mass_schema_apply_action\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaImageObjectBase;

/**
 * Provides a plugin for the 'logo' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_apply_action_logo",
 *   label = @Translation("logo"),
 *   description = @Translation("Logo or image of the How-To page. An image of the item. This can be a URL or a fully described ImageObject."),
 *   name = "logo",
 *   group = "schema_apply_action",
 *   weight = 6,
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
class SchemaApplyActionLogo extends SchemaImageObjectBase {

}
