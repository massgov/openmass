<?php

namespace Drupal\mass_schema_event\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_event_id' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_event_id",
 *   label = @Translation("@id"),
 *   description = @Translation("The ID of event page."),
 *   name = "@id",
 *   group = "schema_event",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaEventId extends SchemaNameBase {

}
