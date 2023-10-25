<?php

namespace Drupal\mass_map;

use Drupal\Core\Link;
use Drupal\mayflower\Helper;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Class MapLocationFetcher.
 *
 * @package Drupal\mass_map
 */
class MapLocationFetcher {

  /**
   * Get location information from nodes.
   *
   * @param array $nids
   *   A list of nids which reference contact info with addresses.
   *
   * @return array
   *   An array of location data to populate locationListing data structure.
   */
  public function getLocations(array $nids) {
    $node_storage = \Drupal::service('entity_type.manager')->getStorage('node');
    $nodes = $node_storage->loadMultiple($nids);

    // Create the locationListing parent data structure contents.
    $locations = [];

    // Create the location filter form data structure.
    // See @molecules/location-filters.md
    $locations['locationFilters'] = [
      'zipcode' => [
        'inputText' => [
          'labelText' => 'Search by city or zip code',
          'required' => FALSE,
          'id' => 'filter-by-location',
          'name' => 'filter-by-location',
          'type' => 'text',
          'placeholder' => 'City, town, or ZIP code',
          'errorMsg' => 'Please select an address in the suggestions. Hit ENTER or start typing to show suggestions.',
        ],
      ],
      'tags' => [
        // @todo consider making this configurable
        [
          'value' => 'wheelchair',
          'id' => 'wheelchair',
          'label' => 'Wheelchair accessible',
          'checked' => 'false',
          'type' => 'checkbox',
          'icon' => 'wheelchair',
        ],
      ],
      'submitButton' => 'Submit',
    ];

    $locations['form'] = [
      'action' => '#',
      'inputs' => [
        [
          'path' => '@molecules/field-submit.twig',
          'data' => [
            'fieldSubmit' => [
              'inputText'     => [
                'labelText'   => 'Filter by city, town or zipcode',
                'required'    => 'false',
                'id'          => 'filter-by-location',
                'name'        => 'filter-by-location',
                'type'        => 'submit',
                'placeholder' => '',
              ],
              'buttonSearch' => [
                'text' => 'Update',
              ],
            ],
          ],
        ],
      ],
    ];

    // Scaffold the googleMap data structure (markers added later).
    // See: @molecules/google-map.md
    // @todo consider making this configurable
    $locations['googleMap']['map']['zoom'] = 16;
    $locations['googleMap']['map']['center'] = [
      'lat' => '42.4072107',
      'lng' => '-71.3824374',
    ];

    // Establish the maximum items per listing page.
    // @todo consider making this configurable somewhere
    $maxItemsPerPage = 8;
    $locations['maxItems'] = $maxItemsPerPage;

    // Populate the resultsHeading data structure.
    // See @molecules/results-heading.md
    $total_locations = count($nids);
    $locations['resultsHeading'] = [
      'numResults' => $total_locations >= $maxItemsPerPage ? '1-' . $maxItemsPerPage : '1-' . $total_locations,
      'totalResults' => $total_locations,
      // No active filters applied by default.
      'tags' => NULL,
    ];

    // Get the location information from the nodes.
    // Use $key vs $nid because PHP data -> JS array wants sequential keys.
    $key = 0;
    foreach ($nodes as $node) {
      $node_type = $node->getType();

      // Get location info from prototype content types.
      if (!in_array($node_type, ['action', 'stacked_layout'])) {
        $locations['googleMap']['markers'][$key] = $this->getLocation($node);
        $locations['imagePromos']['items'][$key] = $this->getContacts($node);
      }
      if ($node_type == 'action') {
        $locations['googleMap']['markers'][$key] = $this->getActionLocation($node);
        $locations['imagePromos']['items'][$key] = $this->getActionContacts($node);
      }
      if ($node_type == 'stacked_layout') {
        $locations['googleMap']['markers'][$key] = $this->getStackedLayoutLocation($node);
        $locations['imagePromos']['items'][$key] = $this->getStackedLayoutContacts($node);
      }

      // Location subtitle.
      if (isset($node->field_location_subtitle) && !$node->field_location_subtitle->isEmpty()) {
        $locations['imagePromos']['items'][$key]['location']['subtitle'] = $node->field_location_subtitle->value;
      }

      // Get location information from location pages.
      // Populate the googleMap.markers and imagePromos data structures.
      $locations['googleMap']['markers'][$key]['infoWindow'] = $locations['imagePromos']['items'][$key]['infoWindow'];
      $locations['googleMap']['markers'][$key]['infoWindow']['name'] = Link::fromTextAndUrl($node->getTitle(), $node->toUrl())->toString();
      $locations['googleMap']['markers'][$key]['infoWindow']['plain_text_title'] = $node->getTitle();
      $locations['googleMap']['markers'][$key]['infoWindow']['description'] = $node->hasField('field_lede') ? $node->field_lede->value : NULL;
      unset($locations['imagePromos']['items'][$key]['infoWindow']);

      // Get the node title and link.
      $locations['imagePromos']['items'][$key]['title'] = [
        'text' => $node->getTitle(),
        'href' => $node->toUrl()->toString(),
        'type' => '',
      ];

      // Get location listing page overview.
      // @todo use a field map array
      $overview = '';
      if (!empty($node->field_lede->value)) {
        $overview = Helper::fieldFullView($node, 'field_lede');
      }
      if (!empty($node->field_overview->value)) {
        $overview = Helper::fieldFullView($node, 'field_overview');
      }

      // Get the description for the node.
      $locations['imagePromos']['items'][$key]['description']['richText'] = [
        'rteElements' => [
          [
            'path' => '@atoms/11-text/raw-html.twig',
            'data' => [
              'rawHtml' => [
                'content' => $overview,
              ],
            ],
          ],
        ],
      ];

      // Set the listing image.
      $locations['imagePromos']['items'][$key]['image'] = '';

      // Get the banner image from the location page node.
      $thumbnail = '';
      if (Helper::isFieldPopulated($node, 'field_bg_narrow') && $node->get('field_bg_narrow')->referencedEntities()) {
        $thumbnail = Helper::getFieldImageUrl($node, 'thumbnail_190_107', 'field_bg_narrow');
      }
      elseif (Helper::isFieldPopulated($node, 'field_bg_wide') && $node->get('field_bg_wide')->referencedEntities()) {
        $thumbnail = Helper::getFieldImageUrl($node, 'thumbnail_190_107', 'field_bg_wide');
      }

      if (Helper::isFieldPopulated($node, 'field_photo') && $node->get('field_photo')->referencedEntities()) {
        $thumbnail = Helper::getFieldImageUrl($node, 'thumbnail_190_107', 'field_photo');
      }

      // If location page has a banner image, use it as the listing item image.
      if ($thumbnail) {
        $locations['imagePromos']['items'][$key]['image'] = [
          'src' => $thumbnail,
          'alt' => $node->getTitle(),
          'href' => '#',
        ];
      }

      // Get the available location icon taxonomy term names and sprites.
      $tags = [];
      if (Helper::isFieldPopulated($node, 'field_location_icons')) {
        $icons = Helper::getReferencedEntitiesFromField($node, 'field_location_icons');

        // Get the icon name (value) for each term.
        foreach ($icons as $term) {
          $field_sprite_name = $term->get('field_sprite_name');
          if ($field_sprite_name->count() > 0) {
            $sprite_name = $field_sprite_name->first()->getValue();
            $sprite = $sprite_name['value'];
          }

          // For filterable icon/term types, create a tag.
          // @todo consider making the accepted values configurable.
          if (in_array($sprite, ['wheelchair', 'open-now'])) {
            $title = $term->getName();
            $tags[] = [
              "label" => $title,
              "icon" => $sprite,
              "id" => $sprite,
            ];
          }
        }
      }

      // Add all filterable icon/terms as this listing's imagePromo tags.
      $locations['imagePromos']['items'][$key]['tags'] = $tags;

      // Create a map link for the node.
      $locations['imagePromos']['items'][$key]['link'] = [
        'text' => "Directions",
        'href' => 'https://www.google.com/maps/place/' . $locations['imagePromos']['items'][$key]['location']['text'],
        'type' => "external",
        'info' => '',
      ];

      if (isset($node->field_ref_contact_info_1) && !$node->field_ref_contact_info_1->isEmpty()) {
        $contact_information_entity = $node->field_ref_contact_info_1->entity;
        // Get phone number from the referenced contact info node.
        if (!empty($contact_information_entity->field_ref_phone_number->entity->field_phone->value)) {
          $phone = $contact_information_entity->field_ref_phone_number->entity->field_phone->value;

          // Add the phone number to the listing item imagePromo.
          $locations['imagePromos']['items'][$key]['phone'] = [
            'label' => t('Phone'),
            'text' => $phone,
          ];
        }

        // Get hours from the referenced contact info node.
        if (!$contact_information_entity->field_ref_hours->isEmpty()) {
          $hours_paragraph = $contact_information_entity->field_ref_hours->entity;
          if (!is_null($hours_paragraph) && !isset($hours_paragraph->field_hours_structured->entity)) {
            $hours = $hours_paragraph->field_hours_structured->view('default');
            if ($hours) {
              $hours = \Drupal::service('renderer')->render($hours);
              $hours = str_replace('<p>', '', $hours);
              $hours = str_replace('</p>', '|', $hours);
              $hours = array_map('trim', explode('|', $hours));
              foreach ($hours as $hour) {
                if (!$hour) {
                  continue;
                }
                $hour = array_map('trim', explode('<br>', $hour));
                $locations['imagePromos']['items'][$key]['hours'][] = [
                  'label' => trim($hour[0]),
                  'text' => trim($hour[1]),
                ];
              }
            }
          }
        }
      }

      $key++;
    }

    // IMPORTANT: imagePromos and googleMap.markers need to be in same order.
    // Sort imagePromos alphabetically by title text by default.
    if (!empty($locations['imagePromos']['items'])) {
      usort($locations['imagePromos']['items'], function ($a, $b) {
        return strcmp($a['title']['text'], $b['title']['text']);
      });
    }
    // Sort map markers alphabetically by infoWindow Name by default.
    if (!empty($locations['googleMap']['markers'])) {
      usort($locations['googleMap']['markers'], function ($a, $b) {
        return strcmp($a['infoWindow']['plain_text_title'], $b['infoWindow']['plain_text_title']);
      });
    }

    // Scaffold out pagination data structure.
    // See @molecules/pagination.md
    $pages = [
      'pages' => [],
    ];

    // Determine the number of pages by imagePromos + max items per page.
    $numPages = 0;
    if (isset($locations['imagePromos']['items'])) {
      $numPages = ceil(count($locations['imagePromos']['items']) / $maxItemsPerPage);
    }

    // Populate the pagination, making the first page active by default.
    for ($i = 1; $i <= $numPages; $i++) {
      $pages['pages'][$i - 1] = [
        'active' => $i === 1 ? TRUE : FALSE,
        'text' => strval($i),
      ];
    }

    // Populate pagination next button, enabled if there are +1 pages.
    $next = [
      'next' => [
        'disabled' => $numPages <= 1 ? TRUE : FALSE,
        'text' => 'Next',
      ],
    ];

    // Populate pagination previous button, disabled by default.
    $prev = [
      'prev' => [
        // Assumes we are sending page 1 active.
        'disabled' => TRUE,
        'text' => 'Previous',
      ],
    ];

    $locations['pagination'] = array_merge($prev, $pages, $next);

    return $locations;
  }

  /**
   * Get location information from node.
   *
   * @param object $node
   *   Current node.
   *
   * @return array
   *   And array containing the location information.
   */
  private function getLocation($node) {
    $location = [];
    $index = &drupal_static(__FUNCTION__);

    if (!is_int($index)) {
      $index = 0;
    }

    if (!empty($node->field_ref_contact_info_1)) {
      $contactField = $node->field_ref_contact_info_1;
      if (!empty($contactField->entity->field_ref_address)) {
        $addressData = $contactField->entity->field_ref_address;
        $location['lat'] = !is_null($addressData->entity) ? $addressData->entity->field_geofield->lat : '';
        $location['lon'] = !is_null($addressData->entity) ? $addressData->entity->field_geofield->lon : '';
      }
    }

    return [
      'position' => [
        'lat' => !empty($location['lat']) ? $location['lat'] : [],
        'lng' => !empty($location['lon']) ? $location['lon'] : [],
      ],
    ];
  }

  /**
   * Get address information from node.
   *
   * @param object $node
   *   Current node.
   *
   * @return array
   *   And array containing the address information.
   */
  private function getContacts($node) {
    $contacts = [];

    if (!empty($node->field_ref_contact_info_1)) {
      foreach ($node->field_ref_contact_info_1 as $entity) {
        $node = Node::load($entity->target_id);
        $address = '';
        if (!empty($node->field_ref_address->entity->field_address_address)) {
          $addressEntity = $node->field_ref_address->entity->field_address_address;
          $address = !empty($addressEntity[0]->address_line1) ? $addressEntity[0]->address_line1 . ', ' : '';
          $address .= !empty($addressEntity[0]->address_line2) ? $addressEntity[0]->address_line2 . ', ' : '';
          $address .= !empty($addressEntity[0]->locality) ? $addressEntity[0]->locality : '';
          $address .= !empty($addressEntity[0]->administrative_area) ? ', ' . $addressEntity[0]->administrative_area : '';
          $address .= !empty($addressEntity[0]->postal_code) ? ' ' . $addressEntity[0]->postal_code : '';
        }
        $phone = $node->field_ref_phone_number->entity;
        $fax = $node->field_ref_fax_number->entity;
        $links = $node->field_ref_links->entity;
        $contacts = [
          'field_phone' => $phone && $phone->field_phone ? $phone->field_phone->value : NULL,
          'field_fax' => $fax && $fax->field_fax ? $fax->field_fax->value : NULL,
          'field_email' => $links && $links->field_email ? $links->field_email->value : NULL,
          'field_address' => $address,
        ];
      }
    }

    return $this->formatContacts($contacts);
  }

  /**
   * Get location information from Right Rail node.
   *
   * @param object $node
   *   Right Rail node.
   *
   * @return array
   *   And array containing the location information.
   */
  private function getActionLocation($node) {
    $location = NULL;

    // The map could be in one of a couple of fields.
    // Use map from the banner if it contains one.
    if (!empty($node->field_action_banner)) {
      foreach ($node->field_action_banner as $banner_id) {
        $banner = Paragraph::load($banner_id->target_id);
        foreach ($banner->field_full_bleed_ref as $full_bleed_id) {
          $full_bleed = Paragraph::load($full_bleed_id->target_id);
          if ($full_bleed->getType() == 'map') {
            $location = $full_bleed->field_map->getValue();
            $location = reset($location);
            break;
          }
        }
        if (!empty($location)) {
          break;
        }
      }
    }
    // If it is not in the header get map point from the details field.
    if (empty($location) && !empty($node->field_action_details)) {
      foreach ($node->field_action_details as $detail_id) {
        $detail = Paragraph::load($detail_id->target_id);
        if ($detail->getType() == 'map') {
          $location = $detail->field_map->getValue();
          $location = reset($location);
          break;
        }
      }
    }
    return [
      'position' => [
        'lat' => $location['lat'],
        'lng' => $location['lon'],
      ],
      'label' => "",
    ];
  }

  /**
   * Get address information from Right Rail node.
   *
   * @param object $node
   *   Right Rail node.
   *
   * @return array
   *   And array containing the address information.
   */
  private function getActionContacts($node) {
    $contacts = [];
    $address = NULL;
    $email = NULL;
    $phone = NULL;

    // The address could be in one of a couple of fields.
    // Use address from the header if it contains one.
    if (!empty($node->field_action_header)) {
      foreach ($node->field_action_header as $header_id) {
        $header = Paragraph::load($header_id->target_id);
        $contacts = $this->getContactData($header);
      }
    }
    if (empty($address) && !empty($node->field_contact_group)) {
      // Next place to check for the address is the contact group field.
      foreach ($node->field_contact_group as $group_id) {
        $group = Paragraph::load($group_id->target_id);
        if ($group->getType() == 'contact_group') {
          $contacts = $this->getContactData($group);
        }
      }
    }
    if (empty($address) && !empty($node->field_action_sidebar)) {
      // Last we check the sidebar for an address.
      foreach ($node->field_action_sidebar as $sidebar_id) {
        $sidebar = Paragraph::load($sidebar_id->target_id);
        if ($sidebar->getType() == 'contact_group') {
          $contacts = $this->getContactData($sidebar);
        }
      }
    }

    return $this->formatContacts($contacts);
  }

  /**
   * Get location information from Stacked Layout node.
   *
   * @param object $node
   *   Stacked Layout node.
   *
   * @return array
   *   And array containing the location information.
   */
  private function getStackedLayoutLocation($node) {
    $location = NULL;

    if (!empty($node->field_bands)) {
      foreach ($node->field_bands as $band_id) {
        // Search the main bands field for location and address information.
        $band = Paragraph::load($band_id->target_id);
        if (!empty($band->field_main)) {
          foreach ($band->field_main as $band_main_id) {
            $band_main = Paragraph::load($band_main_id->target_id);
            if ($band_main->getType() == 'map') {
              $location = $band_main->field_map->getValue();
              $location = reset($location);
              break;
            }
          }
        }
      }
    }
    return [
      'position' => [
        'lat' => $location['lat'],
        'lng' => $location['lon'],
      ],
      'label' => "",
    ];
  }

  /**
   * Get address information from Stacked Layout node.
   *
   * @param object $node
   *   Stacked Layout node.
   *
   * @return array
   *   And array containing the address information.
   */
  private function getStackedLayoutContacts($node) {
    $contacts = [];
    $address = NULL;
    $email = NULL;
    $phone = NULL;

    // Get address from header if it has one.
    if (!empty($node->field_action_header)) {
      foreach ($node->field_action_header as $header_id) {
        $header = Paragraph::load($header_id->target_id);
        $contacts = $this->getContactData($header);
      }
    }
    if (!empty($node->field_bands)) {
      foreach ($node->field_bands as $band_id) {
        // Search the main bands field for location and address information.
        $band = Paragraph::load($band_id->target_id);
        if (!empty($band->field_main)) {
          foreach ($band->field_main as $band_main_id) {
            $band_main = Paragraph::load($band_main_id->target_id);
            if ($band_main->getType() == 'contact_group') {
              $contacts = $this->getContactData($band_main);
            }
          }
        }
        // Check the right rail of 2up bands for address info.
        if (empty($address) && $band->getType() == '2up_stacked_band') {
          if (!empty($band->field_right_rail)) {
            foreach ($band->field_right_rail as $band_rail_id) {
              $band_rail = Paragraph::load($band_rail_id->target_id);
              if ($band_rail->getType() == 'contact_group') {
                $contacts = $this->getContactData($band_rail);
              }
            }
          }
        }
      }
    }
    return $this->formatContacts($contacts);
  }

  /**
   * Get data out of a contact group if it contains one.
   *
   * @param object $contact_group
   *   The contact group paragraph object.
   * @param string $field
   *   The machine name for the field in the contact group paragraph.
   *
   * @return string
   *   The contact data if the group contains one.
   */
  private function getDataContactGroup($contact_group, $field) {
    $data = '';
    if (!empty($contact_group->field_contact_info)) {
      foreach ($contact_group->field_contact_info as $contact_info_id) {
        $contact_info = Paragraph::load($contact_info_id->target_id);
        // Check contact info paragraph for email.
        if ($contact_info->{$field} && !empty($contact_info->{$field}->value)) {
          $data = $contact_info->{$field}->value;
        }
      }
    }
    return $data;
  }

  /**
   * Get Contact data.
   *
   * @param object $region
   *   The region of the paragraph object.
   *
   * @return array
   *   And array containing contact data.
   */
  private function getContactData($region) {
    $fields = ['field_phone', 'field_email', 'field_address'];
    $contacts = [];

    foreach ($fields as $field) {
      $contacts[$field] = $this->getDataContactGroup($region, $field);
    }

    return $contacts;
  }

  /**
   * Format Contacts.
   *
   * @param array $contacts
   *   Contacts data.
   *
   * @return array
   *   And structured array containing location and infoWindow data.
   */
  private function formatContacts(array $contacts) {
    return [
      'location' => [
        'text' => isset($contacts['field_address']) ? $contacts['field_address'] : '',
        'map'  => 'true',
      ],
      'infoWindow' => [
        'name'     => '',
        'phone'    => isset($contacts['field_phone']) ? $contacts['field_phone'] : '',
        'fax'      => isset($contacts['field_fax']) ? $contacts['field_fax'] : '',
        'email'    => isset($contacts['field_email']) ? $contacts['field_email'] : '',
        'address'  => isset($contacts['field_address']) ? $contacts['field_address'] : '',
        'directions' => isset($contacts['field_address']) ? 'https://maps.google.com/?q=' . urlencode($contacts['field_address']) : '',
      ],
    ];
  }

}
