<?php

namespace Drupal\mass_schema_event\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_event_location' meta tag.
 *
 * @MetatagTag(
 *   id = "schema_event_location",
 *   label = @Translation("location"),
 *   description = @Translation("The location of for example where the event is happening, an organization is located, or where an action takes place."),
 *   name = "location",
 *   group = "schema_event",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaEventLocation extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []): array
  {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:summary]';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function output(): array
  {
    $element = parent::output();
    // Decode the value because it was encoded by hook_tokens().
    $addresses = json_decode($this->value(), TRUE);

    $element['#attributes']['content'] = [];

    // Iterate through aech address and add it to the array output.
    foreach ($addresses as $address) {
      // Compute the streetAddress value by combining line1 and line2.
      $address_line = $address['address_line1'];
      if (!empty($address['address_line2'])) {
        $address_line .= ' ' . $address['address_line2'];
      }
      $element['#attributes']['content'][] =
      [
        '@type' => 'Place',
        'address' => [
          '@type' => 'PostalAddress',
          'addressCountry' => $address['country_code'],
          'addressLocality' => $address['locality'],
          'addressRegion' => $address['administrative_area'],
          'postalCode' => $address['postal_code'],
          'streetAddress' => $address_line,
        ],
      ];
    }

    return $element;
  }

}
