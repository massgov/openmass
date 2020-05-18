<?php

namespace Drupal\mass_schema_special_announcement\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_special_announcement_id' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_special_announcement_id",
 *   label = @Translation("@id"),
 *   description = @Translation("The ID of special_announcement."),
 *   name = "@id",
 *   group = "schema_special_announcement",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaSpecialAnnouncementId extends SchemaNameBase {

}
