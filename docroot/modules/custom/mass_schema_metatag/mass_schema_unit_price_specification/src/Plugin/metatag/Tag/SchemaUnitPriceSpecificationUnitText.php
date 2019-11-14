<?php

namespace Drupal\mass_schema_unit_price_specification\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'unitText' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_unit_price_specification_unit_text",
 *   label = @Translation("unitText"),
 *   description = @Translation("A string or text indicating the unit of measurement. Useful if you cannot provide a standard unit code for unitCode."),
 *   name = "unitText",
 *   group = "schema_unit_price_specification",
 *   weight = 6,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaUnitPriceSpecificationUnitText extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:summary]';
    return $form;
  }

}
