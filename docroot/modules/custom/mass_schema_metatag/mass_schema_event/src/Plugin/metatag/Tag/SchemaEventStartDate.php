<?php

namespace Drupal\mass_schema_event\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_event_start_date' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_event_start_date",
 *   label = @Translation("startDate"),
 *   description = @Translation("The start date and time of the item (in ISO 8601 date format)."),
 *   name = "startDate",
 *   group = "schema_event",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "date",
 *   tree_parent = {},
 *   tree_depth = 0
 * )
 */
class SchemaEventStartDate extends SchemaNameBase {

}
