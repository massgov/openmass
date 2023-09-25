<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;
use Drupal\node\NodeInterface;

/**
 * Provides a plugin for the 'mg_address' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_address",
 *   label = @Translation("mg_address"),
 *   description = @Translation("The address of the location."),
 *   name = "mg_address",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagAddress extends MetaNameBase {

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
              'content' => $address_string,
            ],
          ];
        }
      }
    }
    return $element;
  }

}
