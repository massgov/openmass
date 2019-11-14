<?php

namespace Drupal\mass_schema_government_service\Plugin\metatag\Group;

use Drupal\schema_metatag\Plugin\metatag\Group\SchemaGroupBase;

/**
 * Provides a plugin for the 'GovernmentService' meta tag group.
 *
 * @MetatagGroup(
 *   id = "schema_government_service",
 *   label = @Translation("Schema.org: Government Service"),
 *   description = @Translation("See Schema.org definitions for this Schema type at <a href="":url"">:url</a>.", arguments = { ":url" = "http://schema.org/GovernmentService"}),
 *   weight = 10,
 * )
 */
class SchemaGovernmentService extends SchemaGroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
