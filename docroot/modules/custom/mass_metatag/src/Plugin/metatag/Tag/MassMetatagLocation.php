<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_location' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_location",
 *   label = @Translation("mg_location"),
 *   description = @Translation("The location for this page."),
 *   name = "mg_location",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagLocation extends MetaNameBase {

  /**
   * {@inheritdoc}
   */
  public function output(): array
  {
    $element = parent::output();
    // Decode the value because it was encoded by hook_tokens().
    $addresses = json_decode($this->value(), TRUE);

    $element['#attributes']['content'] = [];

    $address_strings = [];
    // Iterate through aech address and add it to the array output.
    foreach ($addresses as $address) {
      // Compute the streetAddress value by combining line1 and line2.
      $address_line = $address['address_line1'];
      if (!empty($address['address_line2'])) {
        $address_line .= ' ' . $address['address_line2'];
      }

      $address_strings[] = $address_line . ' ' .
        (!empty($address['locality']) ? $address['locality'] . ', ' : '') .
        (!empty($address['administrative_area']) ? $address['administrative_area'] . ' ' : '') .
        (!empty($address['postal_code']) ? $address['postal_code'] : '');
    }

    $element['#attributes']['content'] = json_encode($address_strings);

    return $element;
  }

}
