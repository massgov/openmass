<?php

namespace Drupal\mass_schema_event\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_event_offers' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_event_offers",
 *   label = @Translation("offers"),
 *   description = @Translation("An offer to provide this item—for example, an offer to sell a product, rent the DVD of a movie, perform a service, or give away tickets to an event."),
 *   name = "offers",
 *   group = "schema_event",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaEventOffers extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []): array {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_guide_page_lede]';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function output(): array {
    $element = parent::output();

    if (!empty($element)) {

      $value = str_replace('$', '', $this->value());
      $element['#attributes']['content'] = [
        '@type' => 'Offer',
        'price' => $value,
        'priceCurrency' => 'USD',
      ];
    }

    return $element;
  }

}
