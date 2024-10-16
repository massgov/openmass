<?php

namespace Drupal\mass_schema_special_announcement\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'TravelBans' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_special_announcement_travel_bans",
 *   label = @Translation("travelBans"),
 *   description = @Translation("Url or webcontent for travelBans."),
 *   name = "travelBans",
 *   group = "schema_special_announcement",
 *   weight = 11,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "creative_work",
 *   tree_parent = {
 *      "CreativeWork",
 *    },
 *    tree_depth = -1,
 * )
 */
class SchemaSpecialAnnouncementTravelBans extends SchemaNameBase {

}
