<?php

namespace Drupal\mass_schema_special_announcement\Plugin\metatag\Tag;

use Drupal\mass_schema_metatag\Plugin\metatag\Tag\SchemaWebContentBase;

/**
 * Provides a plugin for the 'QuarantineGuidelines' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_special_announcement_quarantine_guidelines",
 *   label = @Translation("quarantineGuidelines"),
 *   description = @Translation("Url or webcontent for quarantineGuidelines."),
 *   name = "quarantineGuidelines",
 *   group = "schema_special_announcement",
 *   weight = 11,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaSpecialAnnouncementQuarantineGuidelines extends SchemaWebContentBase {

}
