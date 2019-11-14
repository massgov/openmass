<?php

namespace Drupal\mass_schema_event\Plugin\metatag\Group;

use Drupal\schema_metatag\Plugin\metatag\Group\SchemaGroupBase;

/**
 * Provides a plugin for the 'CollectionPage' meta tag group.
 *
 * @MetatagGroup(
 *   id = "schema_event",
 *   label = @Translation("Schema.org: Event"),
 *   description = @Translation("See Schema.org definitions for this Schema type at <a href="":url"">:url</a>.", arguments = { ":url" = "http://schema.org/Event"}),
 *   weight = 10,
 * )
 */
class SchemaEvent extends SchemaGroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
