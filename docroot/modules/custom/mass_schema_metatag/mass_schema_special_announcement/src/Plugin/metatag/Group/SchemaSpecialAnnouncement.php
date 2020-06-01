<?php

namespace Drupal\mass_schema_special_announcement\Plugin\metatag\Group;

use Drupal\schema_metatag\Plugin\metatag\Group\SchemaGroupBase;

/**
 * Provides a plugin for the 'Special Announcement' meta tag group.
 *
 * @MetatagGroup(
 *   id = "schema_special_announcement",
 *   label = @Translation("Schema.org: SpecialAnnouncement"),
 *   description = @Translation("See Schema.org definitions for this Schema type at <a href="":url"">:url</a>.", arguments = { ":url" = "https://schema.org/SpecialAnnouncement"}),
 *   weight = 10,
 * )
 */
class SchemaSpecialAnnouncement extends SchemaGroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
