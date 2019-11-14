<?php

namespace Drupal\mass_schema_government_service\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_government_service_id' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_government_service_id",
 *   label = @Translation("@id"),
 *   description = @Translation("The ID of government service."),
 *   name = "@id",
 *   group = "schema_government_service",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaGovernmentServiceId extends SchemaNameBase {

}
