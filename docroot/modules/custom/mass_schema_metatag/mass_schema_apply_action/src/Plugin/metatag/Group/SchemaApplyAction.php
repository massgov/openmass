<?php

namespace Drupal\mass_schema_apply_action\Plugin\metatag\Group;

use Drupal\schema_metatag\Plugin\metatag\Group\SchemaGroupBase;

/**
 * Provides a plugin for the 'ApplyAction' meta tag group.
 *
 * @MetatagGroup(
 *   id = "schema_apply_action",
 *   label = @Translation("Schema.org: Apply Action"),
 *   description = @Translation("See Schema.org definitions for this Schema type at <a href="":url"">:url</a>.", arguments = { ":url" = "http://schema.org/ApplyAction"}),
 *   weight = 10,
 * )
 */
class SchemaApplyAction extends SchemaGroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
