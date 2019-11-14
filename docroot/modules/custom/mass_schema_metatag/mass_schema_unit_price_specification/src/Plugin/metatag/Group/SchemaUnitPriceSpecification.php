<?php

namespace Drupal\mass_schema_unit_price_specification\Plugin\metatag\Group;

use Drupal\schema_metatag\Plugin\metatag\Group\SchemaGroupBase;

/**
 * Provides a plugin for the 'UnitPriceSpecification' meta tag group.
 *
 * @MetatagGroup(
 *   id = "schema_unit_price_specification",
 *   label = @Translation("Schema.org: Unit Price Specification"),
 *   description = @Translation("See Schema.org definitions for this Schema type at <a href="":url"">:url</a>.", arguments = { ":url" = "http://schema.org/UnitPriceSpecification"}),
 *   weight = 10,
 * )
 */
class SchemaUnitPriceSpecification extends SchemaGroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
