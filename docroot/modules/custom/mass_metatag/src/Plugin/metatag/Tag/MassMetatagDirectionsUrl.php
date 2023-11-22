<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;
use Drupal\node\NodeInterface;

/**
 * Provides a plugin for the 'mg_key_actions' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_directions_url",
 *   label = @Translation("mg_directions_url"),
 *   description = @Translation("The url to the directions of the page."),
 *   name = "mg_directions_url",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagDirectionsUrl extends MetaNameBase {

  /**
   * {@inheritdoc}
   */
  public function output() {
    $element = parent::output();

    $node = \Drupal::routeMatch()->getParameter('node');

    // For Event nodes, pull information from 'Unique Address' field or address
    // from Contact Information.
    if ($node instanceof NodeInterface && $node->bundle() == 'event') {

      $addresses = _mass_metatag_addresses($node);

      if (count($addresses)) {

        // Format the first field entry into proper string.
        $address_string = _mass_metatag_address_format($addresses[0]);

        // Only show if there is an address string.
        if ($address_string) {
          $element = [
            '#tag' => 'meta',
            '#attributes' => [
              'name' => $this->name,
              'content' => 'https://maps.google.com/?q=' . urlencode($address_string),
            ],
          ];
        }
      }
    }
    return $element;
  }

}
