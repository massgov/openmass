<?php

namespace Drupal\mass_schema_government_service\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaImageBase;

/**
 * Provides a plugin for the 'logo' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_government_service_logo",
 *   label = @Translation("logo"),
 *   description = @Translation("Logo of the service."),
 *   name = "logo",
 *   group = "schema_government_service",
 *   weight = 6,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaGovernmentServiceLogo extends SchemaImageBase {

}
