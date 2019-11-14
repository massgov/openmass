<?php

namespace Drupal\mass_schema_unit_price_specification\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'name' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_unit_price_specification_name",
 *   label = @Translation("name"),
 *   description = @Translation("The name of the item."),
 *   name = "name",
 *   group = "schema_unit_price_specification",
 *   weight = 6,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaUnitPriceSpecificationName extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_name]';
    return $form;
  }

}
