<?php

namespace Drupal\mass_schema_special_announcement\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaUrlWebContentBase;

/**
 * Provides a plugin for the 'schema_special_announcement_public_transport_closures_info' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_special_announcement_public_transport_closures_info",
 *   label = @Translation("publicTransportClosuresInfo"),
 *   description = @Translation("Url or webcontent for publicTransportClosuresInfo."),
 *   name = "publicTransportClosuresInfo",
 *   group = "schema_special_announcement",
 *   weight = 11,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaSpecialAnnouncementPublicTransportClosuresInfo extends SchemaUrlWebContentBase {

}
