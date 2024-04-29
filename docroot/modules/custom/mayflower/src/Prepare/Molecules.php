<?php

namespace Drupal\mayflower\Prepare;

use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\link\Plugin\Field\FieldType\LinkItem;
use Drupal\mayflower\Helper;
use Drupal\media\MediaInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Provides variable structure for mayflower molecules using prepare functions.
 */
class Molecules {

  /**
   * Returns the actionSeqList variable structure for the mayflower template.
   *
   * @param object $entity
   *   The object that contains the fields for the sequential list.
   *
   * @see @molecules/action-sequential-list.twig
   *
   * @return array
   *   Returns an array of elements that contains:
   *   [[
   *     "title": "My Title",
   *     "rteElements": [[
   *       "path": "@atoms/11-text/paragraph.twig",
   *       "data": [
   *         "paragraph": [
   *           "text": "My Paragraph Text"
   *         ]
   *       ]
   *     ], ...]
   *   ], ...]
   */
  public static function prepareActionSeqList($entity) {
    $actionSeqLists = [];

    // Creates a map of fields on the parent entity.
    $map = [
      'reference' => ['field_action_step_numbered_items'],
    ];

    // Determines which fieldnames to use from the map.
    $fields = Helper::getMappedFields($entity, $map);

    // Retrieves the referenced field from the entity.
    $items = Helper::getReferencedEntitiesFromField($entity, $fields['reference']);

    // Creates a map of fields that are on the referenced entitiy.
    $referenced_fields_map = [
      'title' => ['field_title'],
      'content' => ['field_content'],
    ];

    // Determines the fieldsnames to use on the refrenced entity.
    $referenced_fields = Helper::getMappedReferenceFields($items, $referenced_fields_map);

    // Creates the actionSeqLists array structure.
    if (!empty($items)) {
      foreach ($items as $id => $item) {
        $actionSeqLists[$id] = [];
        $actionSeqLists[$id]['title'] = Helper::fieldFullView($item, $referenced_fields['title']);
        $actionSeqLists[$id]['richText']['rteElements'][] = [
          'path' => '@atoms/11-text/paragraph.twig',
          'data' => [
            'paragraph' => [
              'text' => Helper::fieldFullView($item, $referenced_fields['content']),
            ],
          ],
        ];
      }
    }

    return $actionSeqLists;
  }

  /**
   * Returns the actionStep variable structure.
   *
   * @param object $entity
   *   The object that contains the fields for the sequential list.
   * @param array $referenced_fields
   *   The reference fields on the entity.
   * @param array $options
   *   The static options for action steps: accordion, isExpanded, etc.
   *
   * @see @molecules/action-step.twig
   *
   * @return array
   *   Return a structured array.
   */
  public static function prepareActionStep($entity, array $referenced_fields, array $options) {
    $downloadLinks = [];

    $actionStep = [
      'accordion' => isset($options['accordion']) ? $options['accordion'] : FALSE,
      'isExpanded' => isset($options['expanded']) ? $options['expanded'] : TRUE,
      'accordionLabel' => isset($options['label']) ? $options['label'] : '',
      'icon' => isset($options['icon_path']) ? $options['icon_path'] : '',
      'title' => Helper::fieldFullView($entity, $referenced_fields['title']),
      'richText' => [
        'rteElements' => [
          Atoms::prepareTextField($entity, $referenced_fields['content']),
        ],
      ],
    ];

    if (array_key_exists('downloads', $referenced_fields)) {
      $downloadLinks = Helper::isFieldPopulated($entity, $referenced_fields['downloads']) ? Organisms::prepareFormDownloads($entity) : [];
    }

    if (array_key_exists('more_link', $referenced_fields)) {
      $actionStep['decorativeLink'] = Helper::isFieldPopulated($entity, $referenced_fields['more_link']) ? Helper::separatedLink($entity->get($referenced_fields['more_link'])[0]) : [];
    }

    return array_merge($actionStep, $downloadLinks);
  }

  /**
   * Returns the imagePromo variable structure.
   *
   * @param object $entity
   *   The object that contains the fields for the sequential list.
   * @param array $fields
   *   The reference fields on the entity.
   * @param array $options
   *   Allow the title to be set via an options array.
   *
   * @see @molecules/image-promo.twig
   *
   * @return array
   *   Return a structured array.
   */
  public static function prepareImagePromo($entity, array $fields, array $options) {
    $imagePromo = [];
    $href = '';
    if (!empty($fields['link']) && $entity->{$fields['link']}->entity) {
      $href = $entity->{$fields['link']}->entity->toURL()->toString();
    }
    $title = '';

    if (array_key_exists('image', $fields)) {
      if (Helper::isFieldPopulated($entity, $fields['image'])) {
        $src = '';

        if (Helper::fieldValue($entity, $fields['image'])) {
          $src = Helper::fieldValue($entity, $fields['image']);
          $src = \Drupal::service('file_url_generator')->generateAbsoluteString($src);
        }
        else {
          $src = Helper::getFieldImageUrl($entity, 'activities_image', $fields['image']);
        }

        $alt = '';
        if ($entity->{$fields['image']}->alt) {
          $alt = $entity->{$fields['image']}->alt;
        }
        else {
          $alt = $options['alt'];
        }

        $ariaHidden = $options['ariaHidden'] ?? '';

        $imagePromo['image'] = [
          'src' => $src,
          'alt' => $alt,
          'href' => $href,
          'ariaHidden' => $ariaHidden,
        ];

      }
    }

    if (array_key_exists('title', $options)) {
      $title = $options['title'];
    }
    elseif (array_key_exists('title', $fields)) {
      if (Helper::isFieldPopulated($entity, $fields['title'])) {
        $title = Helper::fieldValue($entity, $fields['title']);
      }
    }

    $imagePromo['title'] = [
      'text' => $title,
      'href' => $href,
    ];

    if (array_key_exists('lede', $fields)) {
      if (Helper::isFieldPopulated($entity, $fields['lede'])) {
        $imagePromo['description'] = [
          'richText' => [
            'rteElements' => [
              Atoms::prepareTextField($entity, $fields['lede']),
            ],
          ],
        ];
      }
    }

    if (array_key_exists('link', $fields)) {
      if (Helper::isFieldPopulated($entity, $fields['link'])) {
        $imagePromo['link'] = [
          'text' => 'More',
          'href' => $href,
          'type' => 'chevron',
          'info' => t('Read More about @activity', ['@activity' => $title]),
        ];
      }
    }

    return $imagePromo;
  }

  /**
   * Returns the variables structure required to render calloutLinks template.
   *
   * @param object $entity
   *   The object that contains the link field.
   *
   * @see @molecules/callout-links.twig
   *
   * @return array
   *   Returns an array of items that contains:
   *    [[
   *      "text": "Order a MassParks Pass online through Reserve America",
   *      "type": internal/external,
   *      "href": URL,
   *      "info": ""
   *    ], ...]
   */
  public static function prepareCalloutLinks($entity) {
    $map = [
      'link' => ['field_link'],
    ];

    // Determines which fieldnames to use from the map.
    $fields = Helper::getMappedFields($entity, $map);

    // Creates array of links to use in calloutLinks.
    $calloutLinks = Helper::separatedLinks($entity, $fields['link']);

    return $calloutLinks;
  }

  /**
   * Returns the variables structure required to render icon links.
   *
   * @param object $entity
   *   The object that contains the fields.
   * @param array $options
   *   An array containing options.
   *
   * @see @molecules/icon-links.twig
   *
   * @return array
   *   Returns an array of items that contains:
   *    "iconLinks": [
   *      "items":[[
   *        "icon": "icon-name"
   *        "link": [
   *          "href": "https://twitter.com/MassHHS",
   *          "text": "@MassHHS",
   *          "chevron": ""
   *        ]
   *      ], ...]
   *    ]
   */
  public static function prepareIconLinks($entity, array $options = []) {
    $items = [];
    $map = [
      'socialLinks' => ['field_social_links', 'field_services_social_links'],
    ];

    // Determines which fieldnames to use from the map.
    $fields = Helper::getMappedFields($entity, $map);

    // Creates array of links with link parts.
    $links = Helper::separatedLinks($entity, $fields['socialLinks']);

    // Get icons for social links.
    $services = [
      'twitter',
      'facebook',
      'threads',
      'flickr',
      'blog',
      'linkedin',
      'google',
      'instagram',
      'medium',
      'youtube',
    ];

    foreach ($links as $link) {
      $icon = '';

      foreach ($services as $key => $service) {
        if (strpos($link['href'], $service) !== FALSE) {
          $icon = $service;
          break;
        }
      }

      $items[] = [
        'icon' => $icon,
        'link' => $link,
      ];
    }

    return [
      'iconLinks' => [
        'items' => $items,
      ],
    ];
  }

  /**
   * Returns the variables structure required to render sectionLinks template.
   *
   * @param object $entity
   *   The object that contains the title/lede fields.
   * @param array $options
   *   An array containing options.
   * @param array $cache_tags
   *   The array of cache_tags sent in the node render array.
   *
   * @see @molecules/section-links.twig
   *
   * @return array
   *   Returns an array of items that contains:
   *    sectionLinks: {
   *      catIcon: {
   *        icon:
   *        type: string/icon name
   *        small:
   *        type: boolean/true
   *      },
   *      title: {
   *        href:
   *        type: url/required
   *        text:
   *        type: string/required
   *      },
   *      description:
   *      type: string
   *      links: [{
   *        href:
   *        type: url/required
   *        text:
   *        type: string/required
   *      }]
   *    }
   */
  public static function prepareSectionLink($entity, array $options = [], array &$cache_tags = []) {
    $links = [];
    $options += ['noCardLinks' => []];
    $map = [
      'text' => [
        'field_lede',
        'field_service_body',
        'field_guide_page_lede',
        'field_service_lede',
        'field_topic_lede',
        'field_topic_lede',
        'field_sub_title',
      ],
      'icon' => [
        'field_icon_term',
      ],
      'links' => [
        'field_topic_content_cards',
      ],
    ];

    // Determines which field names to use from the map.
    $fields = Helper::getMappedFields($entity, $map);
    $icon = '';
    $cache_tags = array_merge($cache_tags, $entity->getCacheTags());

    if (isset($fields['icon'])) {
      if ($referenced = $entity->{$fields['icon']}->referencedEntities()) {
        $icon = [
          'icon' => $referenced[0]->field_sprite_name->value,
          'small' => 'true',
        ];
      }
    }

    if (isset($fields['links'])) {
      foreach ($entity->{$fields['links']} as $link) {
        $linkEntity = $link->entity;

        if (!empty($linkEntity->field_content_card_link_cards)) {
          foreach ($linkEntity->field_content_card_link_cards as $linkItem) {
            $links[] = Helper::separatedLink($linkItem);
          }
        }
        else {
          $links = Helper::separatedLinks($entity, $fields['links']);
        }
      }

      if (array_key_exists('use4TopLinks', $options)) {
        if (in_array($entity->getType(), $options['use4TopLinks'])) {
          // Only show top 4 links.
          $links = array_slice($links, 0, 4);
        }
      }
    }

    $seeAll = [
      'href' => $entity->toURL()->toString(),
      'text' => 'more',
    ];

    $unique_id = Html::getUniqueId('section_link');

    // Different options for topic_page, org_page, and service_page.
    return [
      'id' => $unique_id,
      'catIcon' => in_array($entity->getType(), isset($options['useIcon']) ? $options['useIcon'] : []) ? $icon : '',
      'title' => [
        'href' => $entity->toURL()->toString(),
        // @todo check if title is being overridden
        'text' => isset($options['title_override']) ? $options['title_override'] : $entity->getTitle(),
      ],
      'description' => !empty($entity->{$fields['text']}->value) ? Helper::fieldValue($entity, $fields['text']) : '',
      'type' => in_array($entity->getType(), isset($options['useCallout']) ? $options['useCallout'] : []) ? 'callout' : '',
      'links' => in_array($entity->getType(), $options['noCardLinks']) ? '' : $links,
      'seeAll' => in_array($entity->getType(), isset($options['noSeeAll']) ? $options['noSeeAll'] : []) ? '' : $seeAll,
    ];
  }

  /**
   * Returns the variables structure required to render alerts.
   *
   * @param object $entity
   *   The object that contains the field.
   * @param string $field
   *   The field name.
   *
   * @see @molecules/callout-alert.twig
   *
   * @return array
   *   Returns a structured array.
   */
  public static function prepareCalloutAlert($entity, $field) {
    $map = [
      'field' => [$field],
    ];

    // Determines which fieldnames to use from the map.
    $fields = Helper::getMappedFields($entity, $map);

    return [
      'path' => '@organisms/by-author/callout-alert.twig',
      'data' => [
        'calloutAlert' => [
          'decorativeLink' => [
            'href' => '',
            'text' => Helper::fieldValue($entity, $fields['field']),
          ],
        ],
      ],
    ];
  }

  /**
   * Returns the variables structure required to render contactGroup template.
   *
   * @param array $entities
   *   An array that containing the $entities for the group.
   * @param array $options
   *   An array containing options.
   *   array(
   *     type: string ('phone' || 'online' || 'email' || 'address' || 'fax')
   *   )
   * @param array &$contactInfo
   *   An array that containing the current schema contact info.
   *
   * @see @molecules/contact-group.twig
   *
   * @return array
   *   Returns an array of items that contains:
   *    contactGroup: {
   *      icon: string / path to icon,
   *      name: string ('Phone' || 'Online' || 'Address' || 'Fax') / optional
   *      items: [{
   *        type: string ('phone' || 'online' || 'email' || 'address' || 'fax' )
   *        property: string / optional
   *        label: string / optional
   *        value: string (html allowed) / required
   *        link: string / optional
   *        details: string / optional
   *      }]
   *    }
   */
  public static function prepareContactGroup(array $entities, array $options, array &$contactInfo) {
    $type = $options['type'];

    switch ($type) {
      case 'address':
        $name = t('Address');
        $icon = 'marker';
        break;

      case 'online':
        $name = t('Online');
        $icon = 'laptop';
        break;

      case 'fax':
        $name = t('Fax');
        $icon = 'fax-icon';
        break;

      case 'phone':
        $name = t('Phone');
        $icon = 'phone';
        break;

      default:
        $name = '';
        $icon = '';
        break;

    }

    $contactGroup = [
      'name' => $name,
      'icon' => $icon,
      'hidden' => '',
      'items' => [],
    ];

    foreach ($entities as $entity) {

      $item = [];

      $item['type'] = $type;

      // Creates a map of fields that are on the entity.
      $map = [
        'details' => ['field_caption'],
        'label' => ['field_label'],
        'hours' => ['field_ref_hours', 'field_hours'],
        'value' => [
          'field_address_address',
          'field_phone',
          'field_fax',
          'field_media_contact_phone',
          'field_ref_phone',
        ],
        'link' => [
          'field_link_single',
          'field_email',
          'field_media_contact_email',
          'field_person_email',
        ],
      ];

      // If this is the 'more_info' contact group, get the url values.
      if (!empty($options['is_more_info'])) {
        // If there is a url value, set the item's link, value, and type.
        // The item is then appended to the contact group's item array.
        if (!empty($entity['url'])) {
          $url = $entity['url'];
          $item['link'] = $url->toString();
          $item['value'] = t('Learn more about this organization');
          $item['type'] = 'online';
          $contactGroup['items'][] = $item;
          // Skip the rest of the logic because we are only dealing with urls.
          continue;
        }
      }

      // Determines which fieldnames to use from the map.
      $fields = Helper::getMappedFields($entity, $map);

      if (array_key_exists('details', $fields) && Helper::isFieldPopulated($entity, $fields['details'])) {
        $item['details'] = Helper::fieldFullView($entity, $fields['details']);
      }

      if (array_key_exists('label', $fields) && Helper::isFieldPopulated($entity, $fields['label'])) {
        $item['label'] = Helper::fieldFullView($entity, $fields['label']);
      }

      if (array_key_exists('hours', $fields) && Helper::isFieldPopulated($entity, $fields['hours'])) {
        $item['hours'] = Helper::fieldFullView($entity, $fields['hours']);
      }

      if ($type == 'address') {
        $address = Helper::formatAddress($entity->{$fields['value']}, $options);
        $item['link'] = $entity->getDirectionsUrl();
        $item['value'] = $address;
        $item['info'] = t('Get directions to ') . $address;

        // Respect first address provided if present.
        if (!$contactInfo['address']) {
          $contactInfo['address'] = $address;
          $contactInfo['hasMap'] = $item['link'];
        }
      }
      elseif ($type == 'fax' || $type == 'phone') {
        $item['value'] = Helper::fieldValue($entity, $fields['value']);
        $item['link'] = str_replace([
          '+',
          '-',
        ], '', filter_var(Helper::fieldValue($entity, $fields['value']), FILTER_SANITIZE_NUMBER_INT));

        // Respect first fax and phone number provided if present.
        if (!$contactInfo[$type]) {
          $contactInfo[$type] = "+1" . $item['link'];
        }
      }
      elseif ($type == 'online') {
        // Checks for email link fields.
        $bundle = $entity->getType();
        $bundles_using_email_fields = [
          'online_email',
          'media_contact',
          'person',
        ];

        if (in_array($bundle, $bundles_using_email_fields)) {
          $link = Helper::separatedEmailLink($entity, $fields['link']);
          $item['link'] = $link['href'];
          $item['value'] = $link['text'];
          $item['type'] = 'email';

          // Respect first email address provided if present.
          if (!$contactInfo['email']) {
            $contactInfo['email'] = $item['link'];
          }
        }
        else {
          $link = Helper::separatedLinks($entity, $fields['link']);
          $item['link'] = $link[0]['href'];
          $item['value'] = $link[0]['text'];
        }
      }

      $contactGroup['items'][] = $item;
    }

    return $contactGroup;
  }

  /**
   * Returns variables structure required to render contactGroup template for addresses.
   *
   * @param object $entity
   *   The address object to be prepared.
   * @param array $options
   *   An array containing options.
   *   array(
   *     type: string ('phone' || 'online' || 'email' || 'address' || 'fax')
   *   )
   *
   * @see @molecules/contact-group.twig
   *
   * @return array
   *   Returns an array of items that contains:
   *    contactGroup: {
   *      icon: string / icon name,
   *      name: string ('Phone' || 'Online' || 'Address' || 'Fax') / optional
   *      items: [{
   *        type: string ('phone' || 'online' || 'email' || 'address' || 'fax'
   *   )
   *        property: string / optional
   *        label: string / optional
   *        value: string (html allowed) / required
   *        link: string / optional
   *        details: string / optional
   *      }]
   *    }
   */
  public static function prepareAddress($entity, array $options) {
    // Verify we are looking at an address.
    if ($entity->getType() != 'address') {
      return [];
    }

    // Set Address defaults.
    $contactGroup = [
      'name' => t('Address'),
      'icon' => 'marker',
      'hidden' => '',
      'items' => [],
    ];

    // Format Address info.
    $item = [];
    $item['type'] = 'address';
    $address = Helper::formatAddress($entity->field_address_address, $options);
    $item['value'] = $address;
    $item['link'] = 'https://maps.google.com/?q=' . urlencode($address);
    $item['info'] = t('Get directions to ') . $address;

    $contactGroup['items'][] = $item;

    return [
      'groups' => [
        $contactGroup,
      ],
    ];
  }

  /**
   * Returns the variables structure required to render calloutLinks template.
   *
   * @param object $entity
   *   The object that contains the link field.
   * @param array $options
   *   An array containing options.
   *   array(
   *     display_title: Boolean / require.
   *   )
   *
   * @see @molecules/contact-us.twig
   *
   * @return array
   *   Returns an array of items that contains:
   *    contactUs: array(
   *      schemaSd: array(
   *        property: string / required,
   *        type: string / required,
   *      ),
   *     title: array(
   *       href: string (url) / optional
   *       text: string (_blank || '') / optional
   *       chevron: boolean / required
   *     ),
   *     groups: [
   *       contactGroup see @molecules/contact-group
   *     ]
   *   )
   */
  public static function prepareContactUs($entity, array $options) {
    $title = '';

    // Create contactInfo object for governmentOrg schema.
    $contactInfo = [
      "address" => "",
      "hasMap" => "",
      "phone" => "",
      "fax" => "",
      "email" => "",
    ];

    // Creates a map of fields that are on the entitiy.
    $reference_map = [
      'address' => ['field_ref_address'],
      'phone' => ['field_ref_phone_number'],
      'online' => ['field_ref_links'],
      'fax' => ['field_ref_fax_number'],
      'more_info' => ['field_contact_more_info_link'],
    ];

    $map = [
      'title' => ['field_display_title', 'field_media_contact_name'],
    ];

    // Determines which field names to use from the map.
    $referenced_fields = Helper::getMappedFields($entity, $reference_map);

    $groups = [];

    // If the address_check isset we only show
    // address if the other ones are empty.
    // This is working on "How to" pages sidebar.
    if (isset($options['address_check'])) {
      if (!empty($referenced_fields)) {
        $available_fields = [];
        foreach ($referenced_fields as $id => $field) {
          if (Helper::isFieldPopulated($entity, $field)) {
            $available_fields[$id] = $field;
          }
        }
      }
    }

    if (!empty($referenced_fields)) {
      foreach ($referenced_fields as $id => $field) {
        if (Helper::isFieldPopulated($entity, $field)) {
          if (isset($options['address_check'])) {
            if (array_key_exists('phone', $available_fields) || array_key_exists('online', $available_fields)) {
              if ($id == 'address') {
                continue;
              }
            }
          }
          // If the field type is a link, get the link data and flag
          // 'is_more_info' to TRUE, so it can be prepared in
          // ::prepareContactGroup().
          if (in_array($field, $reference_map['more_info']) && $entity->get($field)->getFieldDefinition()->getType() == 'link') {
            $items = Helper::dataFromLinkField($entity, ['link' => $field]);
            $options['is_more_info'] = TRUE;
          }
          else {
            $items = Helper::getReferencedEntitiesFromField($entity, $field);
          }
          $groups[] = Molecules::prepareContactGroup($items, $options + ['type' => $id], $contactInfo);

        }
      }
    }
    else {
      $groups[] = Molecules::prepareContactGroup([0 => $entity], $options + ['type' => 'phone'], $contactInfo);
      $groups[] = Molecules::prepareContactGroup([0 => $entity], $options + ['type' => 'online'], $contactInfo);
    }

    $fields = Helper::getMappedFields($entity, $map);

    $display_title = !empty($options['display_title']);
    $only_address = !empty($options['onlyAddress']);
    $only_phone_and_online = !empty($options['onlyPhoneAndOnline']);
    $link_title = isset($options['link_title']) ? $options['link_title'] : FALSE;

    if (isset($fields['title']) && Helper::isFieldPopulated($entity, $fields['title']) && $display_title) {
      if ($link_title) {
        $title = [
          'href' => $entity->toURL()->toString(),
          'text' => $entity->{$fields['title']}->value,
          'chevron' => FALSE,
        ];
      }
      else {
        $title = [
          'text' => $entity->{$fields['title']}->value,
        ];
      }
    }
    elseif ($display_title) {
      if ($link_title) {
        $title = [
          'href' => $entity->toURL()->toString(),
          'text' => $entity->getTitle(),
          'chevron' => FALSE,
        ];
      }
      else {
        $title = [
          'text' => $entity->getTitle(),
        ];
      }
    }

    // If set, only display address.
    if ($only_address && isset($groups[0])) {
      foreach ($groups[0]['items'] as $item) {
        if ($item['type'] == 'address') {
          $groups = array_slice($groups, 0, 1);
          break;
        }
        $groups = '';
      }
    }

    // If set, only display phone and online.
    if ($only_phone_and_online) {
      foreach ($groups as $index => $group) {
        foreach ($group['items'] as $item) {
          if ($item['type'] != 'phone' && $item['type'] != 'online' && $item['type'] != 'email') {
            unset($groups[$index]);
          }
        }
      }
    }

    // Check our groups for value.
    foreach ($groups as $index => $group) {

      // If the value is empty, but the link is in place,
      // we are setting the value to be the same as link.
      if (!empty($group['items'][0]['link']) && empty($group['items'][0]['value'])) {
        $groups[$index]['items'][0]['value'] = $group['items'][0]['link'];
      }
      // If we have an empty group, do not display.
      elseif (empty($group['items'][0]['value'])) {
        unset($groups[$index]);
      }
    }

    if (isset($options['order'])) {
      $reordered_groups = [];
      foreach ($options['order'] as $order) {
        foreach ($groups as $index => $group) {
          if (!empty($group['name'])) {
            foreach ($group['items'] as $item) {
              if ($item['type'] == $order) {
                $extracted_group = array_slice($groups, $index, 1);
                $reordered_groups[] = reset($extracted_group);
                break 2;
              }
              if ($item['type'] == 'email' && $order == 'online') {
                $extracted_group = array_slice($groups, $index, 1);
                $reordered_groups[] = reset($extracted_group);
                break 2;
              }
            }
          }
          if ($order == 'more_info') {
            if (isset($options['is_more_info'])) {
              if ($options['is_more_info'] == TRUE && empty($group['name'])) {
                $extracted_group = array_slice($groups, $index, 1);
                $reordered_groups[] = reset($extracted_group);
              }
            }
          }
        }
      }
      $groups = $reordered_groups;
    }

    return [
      'schemaSd' => [
        'property' => 'containedInPlace',
        'type' => 'CivicStructure',
      ],
      'schemaContactInfo' => $contactInfo,
      'accordion' => $options['accordion'] ?? FALSE,
      'isExpanded' => $options['isExpanded'] ?? FALSE,
      'level' => $options['level'] ?? '',
      // @todo Needs validation if empty or not.
      'subTitle' => $title,
      'groups' => $groups,
    ];
  }

  /**
   * Returns the variables structure required to render key actions.
   *
   * @param object $entity
   *   The object that contains the field.
   * @param string $field
   *   The field name.
   * @param array $options
   *   An array of options including heading data structure.
   *   options = [
   *     heading => [
   *       type = compHeading || sidebarHeading,
   *       title = t('Key Actions'),
   *       sub = FALSE,
   *     ],
   *   ].
   *
   * @see @molecules/callout-alert.twig
   *
   * @return array
   *   Returns a structured array.
   */
  public static function prepareKeyActions($entity, $field = '', array $options = []) {
    $key_actions = [];

    $map = [
      'field' => [$field],
    ];

    // Determines which fieldnames to use from the map.
    $fields = Helper::getMappedFields($entity, $map);

    // Roll up our Key Action links.
    $key_actions['links'] = Helper::separatedLinks($entity, $fields['field']);

    // Populate heading data structure based on options passed.
    if (array_key_exists('heading', $options)) {
      $key_actions[$options['heading']['type']] = $options['heading'];
    }

    return $key_actions;
  }

  /**
   * Returns the variables structure required to render callout stats.
   *
   * @param object $entity
   *   The object that contains the field.
   * @param string $field
   *   The field name.
   * @param array $options
   *   An array of options.
   *
   * @see @molecules/callout-stats.twig
   *
   * @return array
   *   Returns a structured array.
   */
  public static function prepareCalloutStats($entity, $field = '', array $options = []) {
    $map = [
      'field' => [$field],
      'label' => ['field_guide_section_label'],
    ];

    // Determines which fieldnames to use from the map.
    $fields = Helper::getMappedFields($entity, $map);

    return [
      'path' => '@molecules/callout-stats.twig',
      'data' => [
        'statsCallout' => [
          'pull' => $options['pull'],
          'stat' => Helper::fieldValue($entity, $fields['field']),
          'content' => Helper::fieldValue($entity, $fields['label']),
        ],
      ],
    ];
  }

  /**
   * Returns the variables structure required to render googleMap.
   *
   * @param array $contacts
   *   An array of contact entities.
   * @param array $locations
   *   An array of location entities, keyed on contact ID.
   *
   * @see @molecules/google-map.twig
   *
   * @return array
   *   Returns an array of items that contains:
   *    [[
   *      "map": "Order a MassParks Pass online through Reserve America",
   *      "markers": "",
   *    ], ...]
   */
  public static function prepareGoogleMapFromContacts(array $contacts, array $locations = []) {
    $markers = [];

    // Load the first valid entity reference value from a field.
    // Used rather than referencedEntities() to avoid triggering more entity
    // loads than we need to.
    $firstReference = function ($entity, $field_name) {
      foreach ($entity->{$field_name} as $value) {
        if ($value->entity) {
          return $value->entity;
        }
      }
    };

    foreach ($contacts as $entity) {
      $phone_number = '';
      $fax_number = '';
      $link = '';
      $location_link = FALSE;

      // Get Address and Map info.
      if ($addressEntity = $firstReference($entity, 'field_ref_address')) {
        $data[] = [
          0 => $addressEntity->field_geofield->lat,
          1 => $addressEntity->field_geofield->lon,
        ];

        // Get phone numbers.
        if ($phoneEntity = $firstReference($entity, 'field_ref_phone_number')) {
          $phone_number = Helper::fieldValue($phoneEntity, 'field_phone');
        }

        // Get fax numbers.
        if ($faxEntity = $firstReference($entity, 'field_ref_fax_number')) {
          $fax_number = Helper::fieldValue($faxEntity, 'field_fax');
        }

        // @todo This logic is broken.  It's supposed to load an e-mail value
        // from a paragraph's link field (I think), but it doesn't work. Instead,
        // it causes paragraph loads that don't do anything.
        // Get links.
        // @codingStandardsIgnoreStart
        // foreach ($entity->field_ref_links as $link) {
        //  if ($link->getEntity() && $link->getEntity()->hasField('field_link_single')) {
        //    foreach ($link->getEntity()->field_link_single as $linkData) {
        //      $links[] = $linkData->getValue()['title'];
        //    }
        //  }
        // }
        // @codingStandardsIgnoreEnd

        // Generates linked node title for Contact's corresponding Location.
        if (isset($locations[$entity->id()])) {
          $location = $locations[$entity->id()];
          $location_link = Link::fromTextAndUrl($location->label(), $location->toUrl());
        }

        if (!$address = Helper::formatAddress($addressEntity->field_address_address)) {
          $address = '';
        }
        $markers[] = [
          'position' => [
            'alt' => $location_link ? $location_link->getText() : Helper::fieldValue($addressEntity, 'field_label'),
            'lat' => $addressEntity->field_geofield->lat,
            'lng' => $addressEntity->field_geofield->lon,
          ],
          'infoWindow' => [
            // If Contacts are mapped to Locations, provide link to Location.
            'name' => $location_link ? $location_link->toString() : Helper::fieldValue($addressEntity, 'field_label'),
            'phone' => $phone_number,
            'fax' => $fax_number,
            'email' => $link,
            'address' => $address,
            'directions' => 'https://maps.google.com/?q=' . urlencode($address),
          ],
        ];
      }
    }

    // mapProp.
    $actionMap['map']['zoom'] = FALSE;

    if (empty($data)) {
      return [];
    }

    $centers = Helper::getCenterFromDegrees($data);

    $actionMap['map']['center'] = [
      'lat' => $centers[0],
      'lng' => $centers[1],
    ];

    $actionMap['markers'] = $markers;

    return $actionMap;
  }

  /**
   * Returns the variables structure required to render googleMap.
   *
   * @param array $entities
   *   An array of entities.
   * @param string $address
   *   A string address if exists.
   *
   * @see @molecules/google-map.twig
   *
   * @return array
   *   Returns an array of items that contains:
   *    [[
   *      "map": "Order a MassParks Pass online through Reserve America",
   *      "markers": "",
   *    ], ...]
   */
  public static function prepareGoogleMap(array $entities, $address = '') {
    $markers = [];

    foreach ($entities as $index => $marker) {
      $data[] = [
        0 => $marker->lat,
        1 => $marker->lon,
      ];

      $markers[] = [
        'position' => [
          'lat' => $marker->lat,
          'lng' => $marker->lon,
        ],
        'label' => ++$index,
        'infoWindow' => [
          'name' => $marker->name,
          'phone' => '',
          'fax' => '',
          'email' => '',
          'address' => !empty($address) ? $address : '',
        ],
      ];
    }

    // mapProp.
    $actionMap['map']['zoom'] = 12;

    if (empty($data)) {
      return [];
    }

    $centers = Helper::getCenterFromDegrees($data);

    $actionMap['map']['center'] = [
      'lat' => $centers[0],
      'lng' => $centers[1],
    ];

    $actionMap['markers'] = $markers;

    return $actionMap;
  }

  /**
   * Returns the variables structure required to render googleMapSection.
   *
   * @param object $entity
   *   The object that contains the fields.
   *
   * @see @molecules/action-map.twig
   *
   * @return array
   *   Returns an array of items that contains:
   *    [[
   *      "path": "@molecules/action-map.twig",
   *      "data": "[actionMap",
   */
  public static function prepareGoogleMapSection($entity) {
    return [
      'title' => '',
      'into' => '',
      'id' => '',
      'path' => '@molecules/action-map.twig',
      'data' => [
        'actionMap' => Molecules::prepareGoogleMap($entity),
      ],
    ];
  }

  /**
   * Returns the variables structure required to render headerSearch.
   *
   * @param object|null $entity
   *   The object that contains the fields.
   * @param array &$cache_tags
   *   The array of node cache tags.
   *
   * @return array
   *   Returns an array of items that contains:
   *    [[
   *      "path": "@molecules/action-map.twig",
   *      "data": "[actionMap",
   *
   * @see @molecules/header-search.twig
   */
  public static function prepareHeaderSearch(object $entity = NULL, array &$cache_tags = []) {
    $has_suggestions = FALSE;
    $suggested_scopes = [];
    $orgs = [];
    if ($entity instanceof NodeInterface) {

      if ($entity->bundle() === 'org_page') {
        $orgs[] = $entity;
      }

      if ($entity->hasField('field_organizations')) {
        if (!$entity->get('field_organizations')->isEmpty()) {
          $org_field_values = $entity->get('field_organizations')->referencedEntities();
          $orgs = array_merge($orgs, $org_field_values);
        }
      }

      if (!empty($orgs)) {
        foreach ($orgs as $org) {
          if ($org->hasField('field_org_no_search_filter')) {
            if ($org->field_org_no_search_filter->value != 1) {
              $cache_tags = array_merge($cache_tags, $org->getCacheTags());
              $suggested_scopes[] = trim($org->label());
            }
            $parent = $org->field_parent->entity;
            if ($parent) {
              if ($parent->hasField('field_org_no_search_filter')) {
                if ($parent->field_org_no_search_filter->value != 1) {
                  $cache_tags = array_merge($cache_tags, $parent->getCacheTags());
                  $suggested_scopes[] = trim($parent->label());
                }
              }
            }
          }
        }
      }
    }
    if (!empty($suggested_scopes)) {
      $has_suggestions = TRUE;
      $suggested_scopes = array_unique($suggested_scopes);
    }

    return [
      'hasSuggestions' => $has_suggestions,
      'suggestedScopes' => $suggested_scopes,
      'name' => 'header-search',
      'id' => 'header-search',
      'placeholder' => 'Search Mass.gov',
      'label' => 'Search terms',
    ];
  }

  /**
   * Returns the variables structure required to render widgets.
   *
   * @param object $entity
   *   The object that contains the fields.
   * @param string $type
   *   The type of widget to produce.
   *
   * @see @molecules/action-WIDGET.twig
   *
   * @return array
   *   Returns an array of items that contains:
   *    [[
   *      "path": "@molecules/action-WIDGET.twig",
   *      "data": [ 'widget' ],
   *    ], ...]
   */
  public static function prepareWidgets($entity, $type) {
    $widgets = [];

    // Create widgets.
    foreach ($entity as $widget) {
      $widgets[] = [
        'path' => '@molecules/action-' . $type . '.twig',
        'data' => [
          'action' . $type => [
            'name' => [
              'type' => '',
              'href' => '#',
              'text' => '',
              'property' => '',
            ],
            'date' => '',
            'description' => '',
          ],
        ],
      ];
    }

    return $widgets;
  }

  /**
   * Returns the variables structure required to render locationIcons.
   *
   * @param object $entity
   *   The object that contains the fields.
   *
   * @see @molecules/location-icons.twig
   *
   * @return array
   *   Returns an array of items that contains:
   *    [[
   *      "path": "@molecules/icons.twig",
   *      "name": "Taxo name",
   *    ], ...]
   */
  public static function prepareLocationIcons($entity) {
    $icons = [];

    // Get general location icons.
    if (!empty($entity->field_location_icons->referencedEntities())) {
      foreach ($entity->field_location_icons->referencedEntities() as $icon) {
        $icons[] = [
          'path' => $icon->get('field_sprite_name')->value,
          'name' => $icon->getName(),
        ];
      }
    }

    // Get park location icons.
    if (!empty($entity->field_location_icons_park->referencedEntities())) {
      foreach ($entity->field_location_icons_park as $icon) {
        $icons[] = [
          'path' => $icon->entity->get('field_sprite_name')->value,
          'name' => $icon->entity->getName(),
        ];
      }
    }

    return $icons;
  }

  /**
   * Returns the variables structure required to render key actions.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   *   The object that contains the field.
   * @param string $field
   *   The field name.
   * @param array $options
   *   An array of options.
   *
   * @see @molecules/callout-time.twig
   *
   * @return array
   *   Returns a structured array.
   */
  public static function prepareCalloutTime(ContentEntityBase $entity, $field = '', array $options = []) {
    $map = [
      'field' => [$field],
    ];

    // Determines which fieldnames to use from the map.
    $fields = Helper::getMappedFields($entity, $map);

    return [
      'text' => Helper::fieldValue($entity, $fields['field']),
      'icon' => isset($options['icon']) ? $options['icon'] : '',
    ];
  }

  /**
   * Returns the variables structure required to render action downloads.
   *
   * @param object $entity
   *   The object that contains the field.
   * @param array $options
   *   An array of options.
   * @param array $cache_tags
   *   The array of cache_tags sent in the node render array.
   *
   * @see @molecules/action-downloads.twig
   *
   * @return array
   *   Returns a structured array.
   */
  public static function prepareDownloadLink($entity, array $options = [], array &$cache_tags = []) {
    $itsAFile = FALSE;
    $itsALink = FALSE;
    $icon = '';
    $title = '';
    $href = '';
    $file = NULL;
    $description = [];

    if (isset($options['description']) && !empty($options['description'])) {
      $description = [
        'rteElements' => [
          $options['description'],
        ],
      ];
    }

    if ($entity instanceof File) {
      $itsAFile = TRUE;
      $file = $entity;
    }

    if ($entity instanceof MediaInterface) {
      // Get the view results entities' cache tags.
      $cache_tags = array_merge($cache_tags, $entity->getCacheTags());

      // Get the attached file from the media entity.
      $file = $entity->field_upload_file->entity;
      if ($file instanceof File) {
        $itsAFile = TRUE;
        if ($entity->isDefaultRevision()) {
          // Create media entity download link rather than linking directly to file.
          $href = Url::fromRoute(
            'media_entity_download.download',
            [
              'media' => $entity->id(),
            ]
          );
        }
        else {
          $href = $file->createFileUrl();
        }
      }
    }

    // In some instances, non-binary files could be uploaded or referenced.
    // This check simply fails out if the files cannot be loaded.
    if ($itsAFile) {
      // Get file info.
      $bytes = $file->getSize();
      $readable_size = format_size($bytes);
      $title = !empty($entity->field_title->value) ? $entity->field_title->value : $file->getFilename();
      $file_info = new \SplFileInfo($file->getFilename());
      $file_extension = $file_info->getExtension();
      switch (strtolower($file_extension)) {
        case 'pdf':
        case 'docx':
        case 'xlsx':
          $icon = 'doc-' . strtolower($file_extension);
          break;

        default:
          $icon = 'doc-generic';
      }
    }

    // $entity here is actually a link item.
    if ($entity instanceof LinkItem) {
      $item = $entity;
      $itsALink = TRUE;
      $url = $item->getUrl();
      $title = $item->computed_title;
      $href = $url->toString();
      // Determine the entity we're linking to using the helper method.
      if ($link_entity = Helper::entityFromUri($item->getValue()['uri'])) {
        if (empty($title)) {
          // Use the label() method rather than getTitle(), since it's supported by
          // all entity types.
          $title = $link_entity->label();
        }
        // Switch to the laptop icon if we are linking to a form page.
        // Only use methods that are on EntityInterface - it will prevent errors.
        if ($link_entity->getEntityTypeId() === 'node' && $link_entity->bundle() === 'form_page') {
          $icon = 'laptop';
        }
      }
    }

    if ($entity instanceof Node) {
      // Get the view results entities' cache tags.
      $cache_tags = array_merge($cache_tags, $entity->getCacheTags());

      $itsALink = TRUE;
      $title = Helper::fieldValue($entity, 'title');
      $href = $entity->toUrl()->toString();
      if ($entity->getType() == 'form_page') {
        $icon = 'laptop';
      }
    }

    // If we neither have a file nor a link entity then skip this item.
    if (!$itsAFile && !$itsALink) {
      return [];
    }

    return [
      'downloadLink' => [
        'iconSize' => '',
        'icon' => $icon,
        'decorativeLink' => [
          'text' => $title,
          'href' => $href,
          'info' => '',
          'property' => '',
        ],
        'description' => (!empty($description)) ? $description : '',
        'size' => ($itsAFile) ? strtoupper($readable_size) : '',
        'format' => ($itsAFile) ? strtoupper($file_extension) : '',
      ],
    ];
  }

  /**
   * Returns the data structure necessary for sticky nav.
   *
   * @param array $navLinksText
   *   Array of strings (i.e. "What you need") used to generate anchor links.
   * @param string $titleContext
   *   String of title context.
   *
   * @see @molecules/sticky-nav.twig
   *
   * @return array
   *   Returns a structured array.
   */
  public static function prepareStickyNav(array $navLinksText, $titleContext = NULL) {
    $anchorLinks = array_map(function ($text) {
      return [
        "href" => Helper::createIdTitle($text),
        "text" => $text,
        "info" => "",
      ];
    }, $navLinksText);

    // Build an array of anchor IDs for the twig template.
    $anchorIDs = [];
    foreach ($anchorLinks as $value) {
      // Skip headers that are not flexible.
      if (is_string($value['text'])) {
        $anchorIDs[$value['text']] = $value['href'];
      }
    }

    return [
      'titleContext' => $titleContext,
      'anchorLinks' => $anchorLinks,
      'anchorIDs' => $anchorIDs,
    ];
  }

  /**
   * Returns the variables structure required to render event teaser.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   *   The object that contains the fields.
   * @param array $fields
   *   Field objects for contact, date, time, location and lede.
   * @param array $options
   *   headerDate option to set the display to only display date and time.
   *
   * @see @molecules/event-teaser.twig
   *
   * @return array
   *   Returns a structured array.
   */
  public static function prepareEventTeaser(ContentEntityBase $entity, array $fields, array $options = []) {
    // @todo break this prepare method out into smaller build methods.
    $titleUrl = '';
    $titleText = '';
    $location = '';
    $lat = '';
    $lng = '';
    $dateSummary = '';
    $dateStartMonth = '';
    $dateStartDay = '';
    $startDateTime = '';
    $dateEndMonth = '';
    $dateEndDay = '';
    $time = '';
    $description = '';
    $title = [];

    // Get geolocation for event listing teasers.
    if (isset($fields['location']) && !empty($entity->{$fields['location']}->entity)) {
      $contactEntity = $entity->{$fields['location']}->entity;
      // If the event location is an entity reference to a contact.
      if ($fields['location'] == 'field_event_ref_contact' && $address = $contactEntity->field_ref_address->entity) {
        $location = Helper::formatAddress($address->field_address_address);
        $lat = $address->field_geofield->lat;
        $lng = $address->field_geofield->lon;
      }
      // If the event location is a unique address.
      if ($fields['location'] == 'field_event_ref_unique_address') {
        $location = Helper::formatAddress($contactEntity->field_address_address);
        $lat = $contactEntity->field_geofield->lat;
        $lng = $contactEntity->field_geofield->lon;
      }
    }

    // Get timestamps for start / end date (required fields).
    $startTimestamp = $entity->{$fields['date']}->value;
    $endTimestamp = $entity->{$fields['date']}->end_value;

    // Format the dates and times according to style guide.
    [$startDateTime, $startDate, $startTime] = Helper::getMayflowerDate($startTimestamp);
    [$endDateTime, $endDate, $endTime] = Helper::getMayflowerDate($endTimestamp);

    /*
     * Set up the date summary for single day events.
     *
     * Without complex time (default):
     * Friday, August 12, 2017
     * 5 p.m. - 7:30 p.m.
     *
     * With complex time (override):
     * Friday, August 12, 2017
     * This is my complex time
     */
    // Without complex time (default).
    $dateSummary = $startDate;
    $time = $startTime . ' - ' . $endTime;

    // Override default time with complex time, if set.
    $is_complex_time = (!empty($fields['time']) && Helper::isFieldPopulated($entity, $fields['time']));
    if ($is_complex_time) {
      $time = Helper::fieldValue($entity, $fields['time']);
    }

    /*
     * Set up the date summary for multi-day events.
     *
     * With complex time:
     * Friday, August 12, 2017 - Saturday, August 13, 2017
     * This is my complex time
     *
     * Without complex time:
     * Friday, August 12, 2017 5 p.m. -
     * Saturday, August 13, 2017 7 p.m.
     */
    if ($startDate !== $endDate) {
      // Remove the times form the date summary by default, show complex time.
      $dateSummary = $startDate . " - " . $endDate;
      // Add times to start / end date and don't show a time if no complex time.
      if (!$is_complex_time) {
        $dateSummary = $startDate . " " . $startTime . " -\n" . $endDate . " " . $endTime;
        // Leave time blank because we include it in the dateSummary.
        $time = '';
      }
    }

    // Populate date graphic date data for non header/sidebar (i.e. event listing and org/service page row) event teasers.
    if (!isset($options['headerDate']) && !isset($options['sidebarDate'])) {
      // Show summary as date + time for multi-day events with no complex time in event listing and row teasers.
      if ($startDate !== $endDate) {
        if (!$is_complex_time) {
          $time = $startDate . " " . $startTime . " -\n" . $endDate . " " . $endTime;
        }
      }

      $dateStartMonth = $startDateTime->format('M');
      $dateStartDay = $startDateTime->format('d');
      $dateEndMonth = ($startDateTime->format('Y-m-d') === $endDateTime->format('Y-m-d')) ? '' : $endDateTime->format('M');
      $dateEndDay = ($startDateTime->format('Y-m-d') === $endDateTime->format('Y-m-d')) ? '' : $endDateTime->format('d');
    }

    // Unless in the header (i.e. on the event page), link the teaser title to the event page.
    if (!isset($options['headerDate'])) {
      $titleUrl = $entity->toURL()->toString();
      $titleText = Helper::fieldValue($entity, 'title');
      $description = !empty($entity->{$fields['lede']}->value) ? Helper::fieldValue($entity, $fields['lede']) : '';

      $title = [
        'href' => $titleUrl,
        'text' => $titleText,
        'info' => '',
        'property' => '',
      ];
    }

    return [
      'title' => $title,
      'location' => $location,
      'position' => [
        'lat' => $lat,
        'lng' => $lng,
      ],
      'date' => [
        // Removes date summary, if complex time is set.
        // `event-teaser.twig` displays complex time alone, if summary is empty.
        'summary' => (!$is_complex_time) ? $dateSummary : NULL,
        'startMonth' => $dateStartMonth,
        'startDay' => $dateStartDay,
        'startTimestamp' => $startTimestamp,
        'startDate' => $startDate,
        'endMonth' => $dateEndMonth,
        'endDay' => $dateEndDay,
        'endTimestamp' => $endTimestamp,
      ],
      'time' => $time,
      'description' => $description,
    ];
  }

  /**
   * Returns the variables structure required to render pressStatus.
   *
   * @param object $entity
   *   The object that contains the fields.
   *
   * @see @molecules/press-status.twig
   *
   * @return array
   *   Return a structured array:
   */
  public static function preparePressStatus($entity) {
    $names = [];
    $immediateRelease = FALSE;

    $map = [
      'date' => ['field_date_published'],
      'signees' => ['field_news_signees'],
      'news_type' => ['field_news_type'],
    ];

    // Determines which field names to use from the map.
    $field = Helper::getMappedFields($entity, $map);

    if (!empty($field['news_type']) && Helper::fieldValue($entity, $field['news_type']) == 'press_release') {
      $immediateRelease = TRUE;
    }

    if (!empty($field['signees']) && !empty($entity->{$field['signees']}->entity)) {
      foreach ($entity->{$field['signees']}->referencedEntities() as $par_entity) {
        $org_fields = [
          'link' => ['field_state_org_ref_org'],
          'title' => ['field_state_org_name', 'field_external_org_name'],
          'lede' => ['field_state_org_description', 'field_external_org_description'],
          'image' => ['field_state_org_photo', 'field_external_org_photo'],
        ];

        // Determines which field names to use from the map.
        $ref_field = Helper::getMappedFields($par_entity, $org_fields);

        if ($par_entity->getParagraphType()->id == 'state_organization') {
          if (!empty($ref_field['title']) && Helper::isFieldPopulated($par_entity, $ref_field['title'])) {
            $names[]['text'] = Helper::fieldValue($par_entity, $ref_field['title']);
          }
          elseif ($org = $par_entity->field_state_org_ref_org->entity) {
            $names[]['text'] = $org->label();
          }
        }

        if ($par_entity->getParagraphType()->id == 'external_organization') {
          if (!empty($ref_field['title']) && Helper::isFieldPopulated($par_entity, $ref_field['title'])) {
            $names[]['text'] = Helper::fieldValue($par_entity, $ref_field['title']);
          }
        }
      }
    }

    return [
      'title' => ($immediateRelease) ? t("For immediate release:") : '',
      'date' => (!empty($field['date'])) ? Helper::fieldFullView($entity, $field['date']) : '',
      'names' => $names,
    ];
  }

  /**
   * Returns the variables structure required to render press teaser.
   *
   * @param object $entity
   *   An array of objects that contains the fields.
   * @param array $options
   *   An array of options for sidebar contact.
   *
   * @see @molecules/press-teaser.twig
   *
   * @return array
   *   Returns a structured array.
   */
  public static function preparePressTeaser($entity, array $options = []) {
    $date = '';
    $eyebrow = '';
    $image = '';
    $org = '';
    $description = '';

    if (!array_key_exists('displayImages', $options)) {
      $options['displayImages'] = FALSE;
    }

    $url = $entity->toURL();
    $text = $entity->getTitle();

    // https://massgov.atlassian.net/browse/DP-24119
    // We need to keep the content type list to get the same behaviour
    // for dates, since all the fields in the node are sharing the same
    // machine name.
    $ct_allowed_dates = [
      'decision',
      'executive_order',
      'regulation',
      'event',
      'advisory',
      'news',
    ];

    $map = [
      'date' => [
        'field_event_date',
        'field_date_published',
      ],
      'eyebrow' => [
        'field_decision_ref_type',
        'field_advisory_type_tax',
        'field_news_type',
      ],
      'image' => [
        'field_news_image',
      ],
      'description' => [
        'field_news_lede',
      ],
      'org' => [
        'field_news_signees',
      ],
    ];

    // Determines which field names to use from the map.
    $fields = Helper::getMappedFields($entity, $map);

    if (!empty($fields['date']) && in_array($entity->bundle(), $ct_allowed_dates)) {
      $date = new DrupalDateTime($entity->{$fields['date']}->value, new \DateTimeZone('America/New_York'));
      $date = $date->format('n/d/Y');
    }

    if (!empty($fields['eyebrow'])) {
      if (Helper::isEntityReferenceField($entity, $fields['eyebrow']) == TRUE) {
        $type_items = Helper::getReferencedEntitiesFromField($entity, $fields['eyebrow']);

        if ($type_items[0] instanceof Term) {
          $eyebrow = $type_items[0]->getName();
        }
      }
      elseif (Helper::fieldType($entity, $fields['eyebrow']) == 'list_string') {
        if (Helper::isFieldPopulated($entity, $fields['eyebrow'])) {
          $eyebrow = $entity->{$fields['eyebrow']}->first()->view();
        }
      }
    }
    else {
      $eyebrow_content_types = ['executive_order', 'regulation'];
      $content_type = $entity->getType();
      if (in_array($content_type, $eyebrow_content_types)) {
        $eyebrow = $entity->type->entity->label();
      }
    }

    if (!empty($fields['org'])) {
      if (!empty($entity->{$fields['org']}->entity)) {
        $news_signee = $entity->{$fields['org']}->entity;
        // If signee is internal, print title.
        if (Helper::isFieldPopulated($news_signee, 'field_state_org_ref_org')) {
          $org = Helper::getReferenceField($news_signee->get('field_state_org_ref_org'), 'title');
        }

      }
    }

    // On internal item, if empty, grab entity image.
    if (!empty($fields['image']) && $options['displayImages'] == TRUE) {
      $active_theme = \Drupal::service('theme.manager')->getActiveTheme();
      $defaultImageSrc = '/' . $active_theme->getPath() . '/default_images/state-house-dome.jpg';
      $src = Helper::getFieldImageUrl($entity, 'news400x225', $fields['image']);

      if (empty($src)) {
        $src = \Drupal::request()->getSchemeAndHttpHost() . $defaultImageSrc;
      }

      $image = [
        'src' => $src,
        'href' => $url,
      ];
    }

    if (!empty($fields['description'])) {
      $description = [
        'rteElements' => [
          [
            'path' => '@atoms/11-text/paragraph.twig',
            'data' => [
              'paragraph' => [
                'text' => Helper::fieldFullView($entity, $fields['description']),
              ],
            ],
          ],
        ],
      ];
    }
    $result =  [
      'eyebrow' => $eyebrow,
      'title' => [
        'href' => !empty($options['url']) ? $options['url'] : $url,
        'text' => !empty($options['text']) ? $options['text'] : $text,
        'info' => '',
        'property' => '',
      ],
      'date' => $date,
      'org' => $org,
      'description' => $description,
      'image' => $image,
    ];
    if (isset($options['level'])) {
      $result['level'] = $options['level'];
    }
    return $result;
  }

  /**
   * Returns the struction needed to render more links.
   *
   * @param object $entity
   *   The object that contains the fields.
   * @param array $options
   *   Render options for displaying the link.
   *
   * @see @molecules/press-listing
   *
   * @return array
   *   Structured array.
   */
  public static function prepareMoreLink($entity, array $options) {
    // $entity is unused at this point, however for possible future adaptations
    // considerations it is passed to this method.
    return [
      'href' => isset($options['href']) ? $options['href'] : '',
      'text' => isset($options['text']) ? $options['text'] : '',
      'chevron' => isset($options['chevron']) ? $options['chevron'] : TRUE,
      'label' => isset($options['label']) ? $options['label'] : '',
      'labelContext' => isset($options['labelContext']) ? $options['labelContext'] : '',
    ];
  }

  /**
   * Returns the variables structure required to render headerTags.
   *
   * @see @molecules/header-tags.twig
   *
   * @return array
   *   Return a structured array:
   */
  public static function prepareHeaderTags(array $options) {
    $headerTags = [
      'label' => array_key_exists('label', $options) ? $options['label'] : NULL,
      'taxonomyTerms' => array_key_exists('taxonomyTerms', $options) ? $options['taxonomyTerms'] : NULL,
    ];

    return $headerTags;
  }

}
