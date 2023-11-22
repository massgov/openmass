<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;
use Drupal\node\NodeInterface;

/**
 * Provides a plugin for the 'mg_contact_details' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_contact_details",
 *   label = @Translation("mg_contact_details"),
 *   description = @Translation("Details about the phone number of the location."),
 *   name = "mg_contact_details",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagContactDetails extends MetaNameBase {

  /**
   * {@inheritdoc}
   */
  public function output() {

    $element = parent::output();

    try {
      $node = \Drupal::routeMatch()->getParameter('node');
      // For Location nodes, get contact info address, even if not published.
      if ($node instanceof NodeInterface && $node->bundle() == 'location') {
        $info_node = $node->get('field_ref_contact_info_1')->entity;
        // If node is not published, get hours details anyway.
        // If node is published, the token settings will display information.
        if ($info_node && !$info_node->isPublished()) {
          $hours = $info_node
            ->get('field_ref_hours')
            ->entity
            ->get('field_hours_description')
            ->value;
          if ($hours) {
            $element = [
              '#tag' => 'meta',
              '#attributes' => [
                'name' => $this->name,
                'content' => $hours,
              ],
            ];
          }
        }
      }
    }
    catch (\Exception $e) {
      // If there is an error, just return default element.
    }

    return $element;
  }

}
