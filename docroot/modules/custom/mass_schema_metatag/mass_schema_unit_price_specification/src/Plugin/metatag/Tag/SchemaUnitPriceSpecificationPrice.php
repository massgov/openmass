<?php

namespace Drupal\mass_schema_unit_price_specification\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'price' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_unit_price_specification_price",
 *   label = @Translation("price"),
 *   description = @Translation("The offer price of a product, or of a price component when attached to PriceSpecification and its subtypes."),
 *   name = "price",
 *   group = "schema_unit_price_specification",
 *   weight = 6,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaUnitPriceSpecificationPrice extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_price]';
    return $form;
  }

}
