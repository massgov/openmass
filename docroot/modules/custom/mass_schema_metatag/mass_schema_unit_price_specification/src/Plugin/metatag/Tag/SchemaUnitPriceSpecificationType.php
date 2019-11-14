<?php

namespace Drupal\mass_schema_unit_price_specification\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the '@type' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_unit_price_specification_type",
 *   label = @Translation("@type"),
 *   description = @Translation("The offer price of a product, or of a price component when attached to PriceSpecification and its subtypes."),
 *   name = "@type",
 *   group = "schema_unit_price_specification",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaUnitPriceSpecificationType extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = [
      '#type' => 'select',
      '#title' => $this->label(),
      '#description' => $this->description(),
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => [
        'PriceSpecification' => $this->t('PriceSpecification'),
        'CompoundPriceSpecification' => $this->t('CompoundPriceSpecification'),
        'DeliveryChargeSpecification' => $this->t('DeliveryChargeSpecification'),
        'PaymentChargeSpecification' => $this->t('PaymentChargeSpecification'),
        'UnitPriceSpecification' => $this->t('UnitPriceSpecification'),
      ],
      '#default_value' => $this->value(),
    ];
    return $form;
  }

}
