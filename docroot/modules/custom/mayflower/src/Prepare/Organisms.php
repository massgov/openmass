<?php

namespace Drupal\mayflower\Prepare;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\mayflower\Helper;
use Drupal\node\Entity\Node;

/**
 * Provides variable structure for mayflower organisms using prepare functions.
 */
class Organisms {

  /**
   * Returns the variables structure required to render a page header.
   *
   * @param object $entity
   *   The object that contains the necessary fields.
   * @param array $options
   *   The object that contains static data, widgets, and optional content.
   *
   * @see @organisms/page-header/page-header.twig
   *
   * @return array
   *   Returns an array of items.
   *    "pageHeader": [
   *      "title": "Executive Office of Health and Human Services",
   *      "titleNote": "(EOHHS)",
   *      "subTitle": "",
   *      "rteElements": "",
   *      "headerTags": ""
   *      "optionalContents": [[
   *         "path": "[path/to/pattern]",
   *         "data": []
   *       ], ... ],
   *      "divider": false / true,
   *      "widgets": [[
   *         "path": "[path/to/pattern]",
   *         "data": []
   *       ], ... ]
   *    ]
   */
  public static function preparePageHeader($entity, array $options) {
    // Create the map of all possible field names to use.
    $map = [
      'title' => [
        'title',
        'name',
      ],
      'titleNote' => ['field_title_sub_text'],
      'subTitle' => [
        'field_sub_title',
        'field_how_to_lede',
        'field_service_detail_lede',
        'field_location_details_lede',
        'field_location_subtitle',
        'field_form_lede',
        'field_news_lede',
      ],
    ];

    // Determines which field names to use from the map.
    $fields = Helper::getMappedFields($entity, $map);

    // Create the actionHeader data structure.
    $pageHeader = [
      'title' => isset($entity->{$fields['title']}) ? $entity->{$fields['title']}->value : '',
      'titleNote' => isset($fields['titleNote']) ? $entity->{$fields['titleNote']}->value : '',
      'subTitle' => isset($fields['subTitle']) ? $entity->{$fields['subTitle']}->value : '',
      'divider' => array_key_exists('divider', $options) ? $options['divider'] : FALSE,
      'optionalContents' => array_key_exists('optionalContents', $options) ? $options['optionalContents'] : NULL,
      'widgets' => array_key_exists('widgets', $options) ? $options['widgets'] : NULL,
      'category' => array_key_exists('category', $options) ? $options['category'] : NULL,
      'subCategory' => array_key_exists('subCategory', $options) ? $options['subCategory'] : NULL,
      'headerTags' => array_key_exists('headerTags', $options) ? $options['headerTags'] : NULL,
      'publishState' => array_key_exists('publishState', $options) ? $options['publishState'] : NULL,
    ];

    return $pageHeader;
  }

  /**
   * Returns the variables structure required to render a sidebarContact.
   *
   * @param object $entity
   *   The object that contains the necessary fields.
   * @param array $options
   *   An array of options for sidebar contact.
   * @param array &$cache_tags
   *   The array of node cache tags.
   *
   * @see @organisms/by-author/sidebar-contact.twig
   *
   * @return array
   *   Returns an array of items.
   *   'sidebarContact': array(
   *      'coloredHeading': array(
   *        'text': string / required,
   *        'color': string / optional
   *      ),
   *      'items': array(
   *         contactUs see @molecules/contact-us
   *      ).
   */
  public static function prepareSidebarContact($entity, array $options = [], array &$cache_tags = []) {
    $items = [];
    $sidebarContact = [];

    // Create the map of all possible field names to use.
    $map = [
      'items' => [
        'field_ref_contact_info',
        'field_event_contact_general',
      ],
    ];

    // Determines which field names to use from the map.
    $fields = Helper::getMappedFields($entity, $map);

    $ref_items = Helper::getReferencedEntitiesFromField($entity, $fields['items']);

    foreach ($ref_items as $item) {
      // Get entity cache tags.
      $cache_tags = array_merge($cache_tags, $item->getCacheTags());

      $item_options = array_merge($options, ['sidebar' => TRUE, 'display_title' => TRUE]);
      $items[] = ['contactUs' => Molecules::prepareContactUs($item, $item_options)];
    }

    if (!empty($items)) {
      $heading = Helper::buildHeading($options['heading']);
      $sidebarContact = array_merge($heading, ['items' => $items]);
    }

    return $sidebarContact;
  }

  /**
   * Returns the variables structure required to render a contactList.
   *
   * @param object $entity
   *   The object that contains the necessary fields.
   * @param array $options
   *   An array of options for sidebar contact.
   * @param array &$cache_tags
   *   The array of node cache tags.
   *
   * @see @organisms/by-author/contact-list.twig
   *
   * @return array
   *   Returns an array of items.
   *   'contactList': array(
   *      'compHeading': array(
   *        'text': string / required,
   *        'color': string / optional,
   *        'id': string / optional,
   *        'sub': boolean / required if TRUE,
   *        'centered': boolean / required if TRUE,
   *      ),
   *      'contacts': array(
   *         contactUs see @molecules/contact-us
   *      ).
   */
  public static function prepareContactList($entity, array $options = [], array &$cache_tags = []) {
    $contacts = [];

    // Create the map of all possible field names to use.
    $map = [
      'contacts' => [
        'field_how_to_contacts_3',
        'field_news_media_contac',
        'field_press_release_media_contac',
        'field_event_contact_general',
        'field_executive_order_contact',
        'field_decision_ref_contact',
        'field_advisory_ref_contact',
        'field_form_ref_contacts_3',
        'field_regulation_contact',
        'field_service_detail_contact',
        'field_rules_ref_contact',
        'field_ref_contact_info',
        'field_contact',
      ],
    ];

    // Determines which field names to use from the map.
    $fields = Helper::getMappedFields($entity, $map);

    $ref_contacts = Helper::getReferencedEntitiesFromField($entity, $fields['contacts']);

    $options['groups']['display_title'] = TRUE;
    $options['groups']['link_title'] = FALSE;

    foreach ($ref_contacts as $contact) {
      // Get entity cache tags.
      $cache_tags = array_merge($cache_tags, $contact->getCacheTags());

      $contacts[] = Molecules::prepareContactUs($contact, $options['groups']);
    }

    $contactList = [];
    if (!empty($contacts)) {
      // Build a sidebar, comp, or colored heading based on heading type option.
      $heading = [];
      $options['heading']['title'] = isset($options['heading']['title']) ? $options['heading']['title'] : t('Contacts');
      if (!empty($options['heading']['title'])) {
        $heading = Helper::buildHeading($options['heading']);
      }

      $contactList = array_merge($heading, ['contacts' => $contacts]);
    }

    // Create the contactList data structure.
    return $contactList;
  }

  /**
   * Returns the variables structure required to render key actions.
   *
   * @param object $entity
   *   The object that contains the field.
   * @param string $field
   *   The field name.
   * @param array $options
   *   An array of options.
   * @param array &$cache_tags
   *   The array of node cache tags.
   *
   * @see @organisms/by-author/key-actions.twig
   *
   * @return array
   *   Returns a structured array.
   */
  public static function prepareKeyActions($entity, $field = '', array $options = [], array &$cache_tags = []) {
    $map = [
      'field' => [$field],
    ];
    // Determines which fieldnames to use from the map.
    $fields = Helper::getMappedFields($entity, $map);

    $links = Helper::createIllustratedOrCalloutLinks($entity, $fields['field'], $cache_tags);

    $heading = isset($options['heading']) ? Helper::buildHeading($options['heading']) : [];

    return array_merge($heading, ['links' => $links]);
  }

  /**
   * Returns the variables structure required to render eventListing.
   *
   * @param object $entity
   *   The object that contains the field.
   * @param string $field
   *   The field name.
   * @param array $options
   *   An array of options.
   * @param array &$cache_tags
   *   The array of node cache tags.
   *
   * @see @organisms/by-author/event-listing.twig
   *
   * @return array
   *   Returns a structured array.
   */
  public static function prepareEventListing($entity, $field = '', array $options = [], array &$cache_tags = []) {
    // Create the map of event fields.
    $eventFields = [
      'date' => ['field_event_date'],
      'time' => ['field_event_time'],
      'lede' => ['field_event_lede'],
      'contact' => ['field_event_ref_contact'],
    ];

    $has_upcoming = $has_past = FALSE;
    $events = [];

    $now = new DrupalDateTime();

    foreach ($entity->{$field}->referencedEntities() as $eventEntity) {
      if ($eventEntity->isPublished()) {
        $has_past = $has_past || $eventEntity->field_event_date->end_value < $now;
        $has_upcoming = $has_upcoming || $eventEntity->field_event_date->end_value > $now;
        $fields = Helper::getMappedFields($eventEntity, $eventFields);
        $events[] = Molecules::prepareEventTeaser($eventEntity, $fields, $options);
        $cache_tags = array_merge($cache_tags, $eventEntity->getCacheTags());
      }
    }

    if (!$events) {
      return [];
    }

    $more_button = [
      'href' => '',
      'text' => '',
    ];
    // If a max number of items is specified and the page count exceeds it,
    // limit the number of results and add a More button.
    if (array_key_exists('maxItems', $options) && count($events) > $options['maxItems']) {
      $events = array_slice($events, 0, $options['maxItems']);
      if (array_key_exists('moreButton', $options)) {
        $more_button = $options['moreButton'];

        if ($has_past && !$has_upcoming) {
          $more_button['href'] = $more_button['href'] . '/past';
        }
      }
    }

    $heading = isset($options['heading']) ? Helper::buildHeading($options['heading']) : [];
    return array_merge($heading, ['events' => $events, 'more' => $more_button]);
  }

  /**
   * Returns the variables structure required to render link list.
   *
   * @param object $entity
   *   An array of objects that contains the fields.
   * @param string $field
   *   The link / entity reference field name.
   * @param array $options
   *   An array of options for sidebar contact.
   *
   * @see @organisms/by-author/link-list.twig
   *
   * @return array
   *   Returns an array of items that contains:
   *    "linkList" : [
   *      "title": "Related Organizations on www.mass.gov",
   *      "links" : [[
   *        "url":"#",
   *        "text":"Executive Office of Elder Affairs"
   *      ],... ]
   *    ]
   */
  public static function prepareLinkList($entity, $field, array $options = []) {

    $linkList = [];

    // Build description, if option is set.
    if (isset($options['description'])) {
      $description = [
        'rteElements' => [
          [
            'path' => '@atoms/11-text/paragraph.twig',
            'data' => [
              'paragraph' => [
                'text' => $options['description']['text'],
              ],
            ],
          ],
        ],
      ];
    }

    // Roll up the link list.
    $links = Helper::separatedLinks($entity, $field, $options);

    if (!empty($links)) {
      // Build either sidebar or comp heading based on heading type option.
      $heading = isset($options['heading']) ? Helper::buildHeading($options['heading']) : [];
      $linkList = array_merge($heading, ['links' => $links]);
    }
    $linkList['description'] = isset($options['description']) ? $description : '';
    $linkList['stacked'] = isset($options['stacked']) ? $options['stacked'] : '';
    $linkList['sectionClass'] = isset($options['sectionClass']) ? $options['sectionClass'] : '';

    // Sets 'more' link, if available.
    if (!empty($options['more'])) {
      $linkList['more'] = $options['more'];
    }

    return $linkList;
  }

  /**
   * Returns the variables structure required to render press list.
   *
   * @param object $entity
   *   An array of objects that contains the fields.
   * @param string $field
   *   The link / entity reference field name.
   * @param array $options
   *   An array of options for sidebar contact.
   * @param array $secondaryEntities
   *   An array of secondary items.
   * @param array &$cache_tags
   *   The array of node cache tags.
   *
   * @see @organisms/by-author/press-listing.twig
   *
   * @return array
   *   Returns a structured array.
   */
  public static function preparePressListing($entity, $field, array $options = [], array $secondaryEntities = [], array &$cache_tags = []) {
    $items = [];
    $secondaryItems = [];
    $pressList = [];
    $moreLink = '';
    $i = 0;
    $cache_tags[] = 'node_list:news';

    // Get field values.
    $field_values = $entity->get($field);

    foreach ($field_values as $field_value) {
      // If this is an entity, it is processed differently.
      if (!empty($field_value->entity)) {
        if ($field_value->entity->isPublished() === TRUE) {
          // Get entity cache tags.
          $cache_tags = array_merge($cache_tags, $field_value->entity->getCacheTags());
          $items[] = Molecules::preparePressTeaser($field_value->entity, $options);
        }
      }
      // On an internal link item, load the referenced node title.
      // DP-17511: If ref is empty warning can be thrown, adding isset check.
      elseif (isset($field_value->getValue()['uri']) && strpos($field_value->getValue()['uri'], 'entity:node') !== FALSE) {
        $options['url'] = $field_value->getUrl();
        $options['text'] = $field_value->computed_title;
        if (method_exists($options['url'], 'getRouteParameters') && $options['url']->isRouted() == TRUE) {
          $params = $options['url']->getRouteParameters();
          $entity_type = key($params);
          $teaser_entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($params[$entity_type]);
          if (!empty($teaser_entity) && $teaser_entity->isPublished() === TRUE && $teaser_entity instanceof ContentEntityInterface) {
            // Get entity cache tags.
            $cache_tags = array_merge($cache_tags, $teaser_entity->getCacheTags());

            $items[] = Molecules::preparePressTeaser($teaser_entity, $options);
          }
        }
      }
      // DP-17511: Solving issue when referenced entity is deleted.
      elseif (isset($field_value->getValue()['target_id']) && !$field_value->getValue()['_loaded']) {
        continue;
      }
      else {

        $url = $field_value->getUrl();
        $items[] = [
          'title' => [
            'href' => $url,
            'text' => $field_value->getValue()['title'] ?: $url,
          ],
        ];

      }
    }

    if (!empty($secondaryEntities)) {
      foreach ($secondaryEntities as $index => $secondary_entity) {
        // Get entity cache tags.
        $cache_tags = array_merge($cache_tags, $secondary_entity->getCacheTags());

        if (isset($options['numOfSecondaryItems']) && ++$i > (int) $options['numOfSecondaryItems']) {
          break;
        }
        $secondaryItems[] = Molecules::preparePressTeaser($secondary_entity, $options);
      }
    }

    if (!empty($items) || !empty($secondaryItems)) {
      $heading = isset($options['heading']) ? Helper::buildHeading($options['heading']) : [];
      // If a more link was provided, create one from an internal
      // or external reference.
      if (isset($options['numOfSecondaryItems']) && count($secondaryEntities) > $options['numOfSecondaryItems']) {
        if ($entity->getEntityTypeId() === 'paragraph') {
          $node = Helper::getParentNode($entity);
        }
        else {
          $node = $entity;
        }
        $moreOptions = [
          'href' => UrlHelper::filterBadProtocol($node->toUrl()->toString() . '/news'),
          'text' => t('See all news and announcements'),
          'chevron' => TRUE,
          'labelContext' => t('for the @label', ['@label' => $entity->label()]),
        ];
        $moreLink = Molecules::prepareMoreLink($entity, $moreOptions);
      }

      $pressList = array_merge($heading, [
        'items' => $items,
        'secondaryItems' => $secondaryItems,
        'more' => $moreLink,
      ]);
    }

    return $pressList;
  }

  /**
   * Returns the variables structure required to render section Three Up.
   *
   * @param object $entities
   *   The object that contains the fields.
   * @param array $options
   *   An array containing options.
   * @param array $field_map
   *   An optional array of fields.
   * @param array $cache_tags
   *   The array of cache_tags sent in the node render array.
   *
   * @see @organsms/by-author/sections-three-up
   *
   * @return array
   *   Returns structured array.
   */
  public static function prepareSectionThreeUp($entities, array $options = [], array $field_map = NULL, array &$cache_tags = []) {
    $sections = [];
    $fields = [];

    if ($field_map) {
      $fields = Helper::getMappedFields($entities, $field_map);
    }

    // Load up our entity if internal.
    if (isset($fields['topic_cards'])) {
      foreach ($entities->{$fields['topic_cards']} as $card) {
        $url = $card->getUrl();
        $desc = '';
        $seeAll = '';
        // Load up our entity if internal.
        if ($url->isExternal() == FALSE && $url->isRouted() == TRUE && method_exists($url, 'getRouteParameters')) {
          $params = $url->getRouteParameters();
          $entity_type = key($params);
          $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($params[$entity_type]);
          if (!empty($entity)) {
            $link = Helper::separatedLink($card);
            $options = array_merge($options, ["title_override" => $link['text']]);
            $cards[] = Molecules::prepareSectionLink($entity, $options, $cache_tags);
          }
        }
        else {
          $cards[] = [
            'title' => Helper::separatedLink($card),
            'description' => $desc,
            'seeAll' => $seeAll,
          ];
        }
        $sections = $cards;
      }
    }
    else {
      foreach ($entities as $entity) {
        $sections[] = Molecules::prepareSectionLink($entity->entity, $options, $cache_tags);
      }
    }

    $heading = isset($options['heading']) ? Helper::buildHeading($options['heading']) : [];
    return array_merge($heading, ['sections' => $sections]);
  }

  /**
   * Returns the variables structure required for SuggestedPages.
   *
   * @param object $entity
   *   The object that contains the fields.
   * @param array $options
   *   An array of options for title, view, imageStyle.
   * @param array &$cache_tags
   *   The array of node cache tags.
   *
   * @see @organsms/by-author/suggested-pages.twig
   *
   * @return array
   *   Returns structured array.
   */
  public static function prepareSuggestedPages($entity, array $options = [], array &$cache_tags = []) {
    $pages = [];

    // Create the map of all possible field names to use.
    $map = [
      'items' => ['field_related_locations', 'field_guide_page_related_guides'],
    ];
    // Determines which field names to use from the map.
    $fields = Helper::getMappedFields($entity, $map);
    // Get related locations.
    foreach ($entity->{$fields['items']} as $item) {
      $ref_entity = $item->entity;
      // Creates a map of fields that are on the entity.
      if ($ref_entity) {
        if (method_exists($entity, 'isPublished') && !$ref_entity->isPublished()) {
          continue;
        }
        $ref_map = [
          'image' => ['field_bg_narrow', 'field_guide_page_bg_wide'],
        ];
        $cache_tags = array_merge($cache_tags, $ref_entity->getCacheTags());
        // Determines which field names to use from the map.
        $field = Helper::getMappedFields($ref_entity, $ref_map);
        $pages[] = [
          'image' => Helper::getFieldImageUrl($ref_entity, isset($options['style']) ? $options['style'] : '', $field['image']),
          'altTag' => $ref_entity->alt,
          'link' => [
            'type' => UrlHelper::isExternal($ref_entity->toURL()
              ->toString()) ? 'external' : 'internal',
            'href' => $ref_entity->toURL()->toString(),
            'text' => $ref_entity->getTitle(),
          ],
        ];
      }
    }

    // If a max number of items is specified and the page count exceeds it,
    // limit the number of results and add a More button.
    if (array_key_exists('maxItems', $options) && count($pages) > $options['maxItems']) {
      $pages = array_slice($pages, 0, $options['maxItems']);
      if (array_key_exists('moreButton', $options)) {
        $more_button = $options['moreButton'];
      }
    }

    $more_button = [
      'href' => '',
      'text' => '',
    ];

    if (empty($pages)) {
      return [];
    }

    return [
      'title' => isset($options['title']) ? $options['title'] : '',
      'titleContext' => isset($options['titleContext']) ? $options['titleContext'] : '',
      'pages' => $pages,
      'view' => isset($options['view']) ? $options['view'] : '',
      'more' => $more_button,
    ];
  }

  /**
   * Returns the variables structure required to render an jump links.
   *
   * @param array $sections
   *   The sections that contains the necessary fields.
   * @param array $options
   *   The array that contains static data and other options.
   *
   * @see @organisms/by-template/jump-links.twig
   *
   * @return array
   *   Returns a structured array of jump links.
   */
  public static function prepareJumpLinks(array $sections, array $options) {
    $links = [];

    // Create the links data structure.
    foreach ($sections as $section) {
      if (!is_object($section['title']) && !empty($section['title']['compHeading']['title'])) {
        $title = $section['title']['compHeading']['title'];
      }
      else {
        $title = $section['title'];
      }

      $links[] = [
        'text' => $title,
        'href' => $section['id'],
      ];
    }

    if ($links) {
      return [
        'title' => $options['title'],
        'links' => $links,
      ];
    }
    else {
      return [];
    }
  }

  /**
   * Returns the variables structure required to render an Inline Links.
   *
   * @param array $items
   *   Items of the list.
   * @param array $options
   *   'ariaLabel' receives an optional aria-label to show.
   *
   * @see @organisms/by-template/inline-links.twig
   *
   * @return array
   *   Returns a structured array of inline links with language info.
   */
  public static function prepareInlineLinksForLanguages(array $items, array $options) {
    $links = [];

    // Create the links data structure.
    foreach ($items as $item) {

      $links[] = [
        'text' => $item['title'],
        'href' => $item['url'],
        'lang_label' => $item['lang_label'],
      ];
    }

    if ($links) {
      return [
        'ariaLabel' => $options['ariaLabel'],
        'links' => $links,
      ];
    }
    else {
      return [];
    }
  }

  /**
   * Returns the variables structure required to render an Inline Links.
   *
   * @param array $items
   *   Items of the list.
   * @param array $options
   *   'ariaLabel' receives an optional aria-label to show.
   *
   * @see @organisms/by-template/inline-links.twig
   *
   * @return array
   *   Returns a structured array of inline links.
   */
  public static function prepareInlineLinks(array $items, array $options) {
    $links = [];

    // Create the links data structure.
    foreach ($items as $item) {

      $links[] = [
        'text' => $item['title'],
        'href' => $item['url'],
      ];
    }

    if ($links) {
      return [
        'ariaLabel' => $options['ariaLabel'],
        'links' => $links,
      ];
    }
    else {
      return [];
    }
  }

  /**
   * Returns the variables structure required for ActionDetails.
   *
   * @param object $entity
   *   The object that contains the fields.
   * @param object $options
   *   The object that contains static data and other options.
   *
   * @see @organsms/by-author/action-details
   *
   * @return array
   *   Returns structured array.
   */
  public static function prepareActionDetails($entity, $options = NULL) {
    $sections = [];
    $locationType = '';
    $page_title = $entity->get('title')->value;
    if (!empty($options['locationType'])) {
      $locationType = $options['locationType'];
    }

    // Create the map of all possible field names to use.
    $map = [
      'overview' => ['field_overview'],
      'primary_location' => ['field_ref_contact_info_1'],
      'contacts' => ['field_ref_contact_info'],
      'parking' => ['field_parking'],
      'markers' => ['field_maps'],
      'activities' => ['field_location_activity_detail'],
      'facilities' => ['field_location_facilities'],
      'accessibility' => ['field_accessibility'],
      'restrictions' => ['field_restrictions'],
      'services' => ['field_services'],
      'information' => ['field_location_more_information'],
      'all_activities' => ['field_location_all_activities'],
    ];

    // Determines which field names to use from the map.
    $fields = Helper::getMappedFields($entity, $map);

    // Overview section.
    if (!empty($fields['overview']) && Helper::isFieldPopulated($entity, $fields['overview'])) {
      $titleContext = t(' of @title', ['@title' => $page_title]);
      $sections[] = Organisms::prepareRichText($entity, ['field' => 'field_overview', 'titleContext' => $titleContext]);
    }

    // Hours section.
    if (!empty($fields['primary_location']) && Helper::isFieldPopulated($entity, $fields['primary_location'])) {

      $contact_entities[] = Helper::getReferencedEntitiesFromField($entity, $fields['primary_location']);
      foreach ($contact_entities as $contact) {
        $contact_entity = $contact[0];
        // Don't know why this map isn't working.
        // $contact_fields = Helper::getMappedFields($contact_entity, $contact_map)
        $titleContext = t(' of @title', ['@title' => $page_title]);
        $sections[] = Helper::buildHours($contact_entity->field_ref_hours, 'Hours', $titleContext);
      }
    }
    if (!empty($fields['contacts']) && Helper::isFieldPopulated($entity, $fields['contacts'])) {
      $contact_refs[] = Helper::getReferencedEntitiesFromField($entity, $fields['contacts']);
      foreach ($contact_refs as $contact) {
        $contact_ref = $contact[0];
        // Don't know why this map isn't working.
        // $contact_fields = Helper::getMappedFields($contact_entity, $contact_map)
        $sections[] = Helper::buildHours($contact_ref->field_ref_hours, '');
      }
    }

    // Parking section.
    if (!empty($fields['parking']) && Helper::isFieldPopulated($entity, $fields['parking'])) {
      $titleContext = t(' for @title', ['@title' => $page_title]);
      $sections[] = Organisms::prepareRichText($entity, ['field' => 'field_parking', 'titleContext' => $titleContext]);
    }

    // Activities section.
    if (!empty($fields['activities']) && Helper::isFieldPopulated($entity, $fields['activities']) && $locationType == 'park') {
      $title = t('Activities');
      $titleContext = t(' at @title', ['@title' => $page_title]);
      $sections[] = [
        'title' => $title,
        'titleContext' => $titleContext,
        'into' => '',
        'id' => Helper::createIdTitle($title),
        'path' => '@organisms/by-author/image-promos.twig',
        'data' => [
          'imagePromos' => Organisms::prepareImagePromos($entity->{$fields['activities']}),
        ],
      ];
    }

    if (!empty($fields['all_activities']) && Helper::isFieldPopulated($entity, $fields['all_activities']) && $locationType == 'park') {
      $titleContext = t(' at @title', ['@title' => $page_title]);
      // Roll up taxo terms into unordered list.
      $activities = '<ul>';
      foreach ($entity->{$fields['all_activities']} as $activity) {
        $activities .= '<li>' . Helper::fieldValue($activity->entity, 'name') . '</li>';
      }
      $activities .= '</ul>';
      $sections[] = [
        'title' => t('All Activities'),
        'titleContext' => $titleContext,
        'into' => "",
        'id' => t('All Activities'),
        'path' => '@organisms/by-author/rich-text.twig',
        'data' => [
          'richText' => [
            'property' => 'description',
            'rteElements' => [
              [
                'path' => '@atoms/11-text/paragraph.twig',
                'data' => [
                  'paragraph' => [
                    'text' => $activities,
                  ],
                ],
              ],
            ],
          ],
        ],
      ];
    }

    // Facilities section.
    if (!empty($fields['facilities']) && Helper::isFieldPopulated($entity, $fields['facilities'])) {
      $titleContext = t(' at @title', ['@title' => $page_title]);
      $sections[] = Organisms::prepareRichText($entity, ['field' => 'field_location_facilities', 'titleContext' => $titleContext]);
    }

    // Services section.
    if (!empty($fields['services']) && Helper::isFieldPopulated($entity, $fields['services']) && $locationType == 'general') {
      $titleContext = t(' at @title', ['@title' => $page_title]);
      $sections[] = Organisms::prepareRichText($entity, ['field' => 'field_services', 'titleContext' => $titleContext]);
    }

    // Accessibility section.
    if (!empty($fields['accessibility']) && Helper::isFieldPopulated($entity, $fields['accessibility'])) {
      $titleContext = t(' at @title', ['@title' => $page_title]);
      $sections[] = Organisms::prepareRichText($entity, ['field' => 'field_accessibility', 'titleContext' => $titleContext]);
    }

    // Restrictions section.
    if (!empty($fields['restrictions']) && Helper::isFieldPopulated($entity, $fields['restrictions'])) {
      $titleContext = t(' at @title', ['@title' => $page_title]);
      $sections[] = Organisms::prepareRichText($entity, ['field' => 'field_restrictions', 'titleContext' => $titleContext]);
    }

    // More info section.
    if (!empty($fields['information']) && Helper::isFieldPopulated($entity, $fields['information'])) {
      $titleContext = t(' about @title', ['@title' => $page_title]);
      $sections[] = Organisms::prepareRichText($entity, ['field' => 'field_location_more_information', 'titleContext' => $titleContext]);
    }

    return [
      'titleContext' => $page_title,
      'sections' => $sections,
    ];
  }

  /**
   * Returns the variables structure required to render a location banner.
   *
   * @param object $entity
   *   The object that contains the necessary fields.
   * @param object $options
   *   The object that contains static data and other options.
   * @param array &$cache_tags
   *   The array of node cache tags.
   *
   * @see @organisms/by-template/location-banner.twig
   *
   * @return array
   *   Returns an array of items that contains:
   *    [
   *      "bgTitle":"Mt Greylock State Park"
   *      "bgWide":"/assets/images/placeholder/1600x400.png"
   *      "bgNarrow":"/assets/images/placeholder/800x400.png",
   *      "actionMap": "map",
   *    ]
   */
  public static function prepareLocationBanner($entity, $options = NULL, array &$cache_tags = []) {
    $locationBanner = [];

    // Create the map of all possible field names to use.
    $map = [
      'markers' => ['field_maps'],
      'bg_narrow' => ['field_bg_narrow'],
      'bg_wide' => ['field_bg_wide'],
      'contact_info' => ['field_ref_contact_info_1'],
    ];

    // Determines which field names to use from the map.
    $fields = Helper::getMappedFields($entity, $map);

    $locationBanner['bgTitle'] = "";
    // Use helper function to get the image url of a given image style.
    if (!empty($fields['bg_narrow']) && Helper::isFieldPopulated($entity, $fields['bg_narrow'])) {
      $locationBanner['bgNarrow'] = Helper::getFieldImageUrl($entity, 'action_banner_small', $fields['bg_narrow']);
    }
    elseif (!empty($fields['bg_wide']) && Helper::isFieldPopulated($entity, $fields['bg_wide'])) {
      $locationBanner['bgNarrow'] = Helper::getFieldImageUrl($entity, 'action_banner_small', $fields['bg_wide']);
    }
    $locationEntities = Helper::getReferencedEntitiesFromField($entity, $fields['contact_info']);
    foreach ($locationEntities as $locationEntity) {
      // Get entity cache tags.
      $cache_tags = array_merge($cache_tags, $locationEntity->getCacheTags());
    }
    $locationBanner['actionMap'] = Molecules::prepareGoogleMapFromContacts($locationEntities);

    return $locationBanner;
  }

  /**
   * Returns the variables structure required to render a location banner.
   *
   * @param array $locations
   *   An array of location nodes.
   * @param array $options
   *   The array that contains static data and other options.
   * @param array &$cache_tags
   *   The array of node cache tags.
   *
   * @see @organisms/by-author/mapped-locations.twig
   *
   * @return array
   *   Returns an array of items that contains:
   *    "mappedLocations": [
   *      "compHeading": [
   *        "title": "All Locations",
   *        "sub": "",
   *        "color": "",
   *        "id": "",
   *        "centered": ""
   *      ],
   *    "link": [
   *      "href": "#",
   *      "text":"See a list of all locations",
   *      "info": "",
   *      "property": ""
   *    ],
   *    "googleMap": [
   *      "map": [
   *        "center": [
   *          "lat": 42.366565,
   *          "lng": -71.058940
   *        ],
   *        "zoom": 16
   *      ],
   *      "markers": [
   *      [
   *          "position": [
   *            "lat": 42.366565,
   *            "lng": -71.058940
   *          ],
   *        "label": "A",
   *        "infoWindow": [
   *          "name": "Department of Conservation and Recreation",
   *          "phone": "16176261250",
   *          "fax": "16176261351",
   *          "email": "mass.parks@state.ma.us",
   *          "address": "251 Causeway Street, Suite 900\nBoston, MA 02114-2104"
   *        ]
   *      ],
   *    ]]
   *   ]
   *   ]
   */
  public static function prepareMappedLocations(array $locations, array $options, array &$cache_tags = []) {
    // Array for mapping Contact entities to corresponding Location entities.
    $contact_location_map = [];

    $contact_ids = [];
    foreach ($locations as $location) {
      foreach ($location->field_ref_contact_info_1 as $contactRef) {
        $contactId = $contactRef->target_id;
        $contact_ids[] = $contactId;
        $contact_location_map[$contactId] = $location;
      }
    }
    // Batch load contact entities all at once.
    $contact_entities = Node::loadMultiple($contact_ids);

    // Override the link to the map of locations if there is only a single
    // location listed and instead link directly to that single location's page.
    $href = $options['locationDetailsLink']['path'] . '/locations';
    if (count($locations) === 1) {
      $location = reset($locations);
      $href = $location->toUrl()->toString();
    }

    $link = [
      'href' => $href,
      'text' => !empty($options['aside']['button']) ? $options['aside']['button'] : t('Location Details'),
      'chevron' => 'true',
    ];

    $heading = isset($options['heading']) ? Helper::buildHeading($options['heading']) : [];

    $link = isset($options['locationDetailsLink']['display']) ? $link : [];

    $googleMap = Molecules::prepareGoogleMapFromContacts($contact_entities, $contact_location_map);

    $paragraph = isset($options['aside']['paragraph']) ? $options['aside']['paragraph'] : [];

    return array_merge($heading, [
      'leafletMap' => $googleMap,
      'button' => $link,
      'paragraph' => $paragraph,
    ]);
  }

  /**
   * Returns the variables structure required for richText.
   *
   * @param object $entity
   *   The object that contains the fields.
   * @param array $options
   *   This is an array of field names.
   *
   * @see @organsms/by-author/rich-text.twig
   *
   * @return array
   *   Returns structured array.
   */
  public static function prepareRichText($entity, array $options = []) {
    $richTextWithTitle = [];
    foreach ($options as $field) {
      if (Helper::isFieldPopulated($entity, $field)) {
        $richTextWithTitle = [
          'title' => $entity->$field->getFieldDefinition()->getLabel(),
          'titleContext' => isset($options['titleContext']) ? $options['titleContext'] : '',
          'into' => "",
          'id' => $entity->{$field}->getFieldDefinition()->getLabel(),
          'path' => '@organisms/by-author/rich-text.twig',
          'data' => [
            'richText' => [
              'property' => 'description',
              'rteElements' => [
                [
                  'path' => '@atoms/11-text/paragraph.twig',
                  'data' => [
                    'paragraph' => [
                      'text' => $entity->{$field}->value,
                    ],
                  ],
                ],
              ],
            ],
          ],
        ];
      }
    }
    return $richTextWithTitle;
  }

  /**
   * Returns the variables structure required for richText paragraph.
   *
   * @param object $entity
   *   The object that contains the fields.
   * @param string $field
   *   The field name.
   * @param array $options
   *   This is an array of field names.
   *
   * @see @organsms/by-author/rich-text.twig
   *
   * @return array
   *   Returns structured array.
   */
  public static function prepareRichTextParagraph($entity, $field = '', array $options = []) {
    $map = [
      'field' => [$field],
    ];

    // Determines which field names to use from the map.
    $fields = Helper::getMappedFields($entity, $map);
    return [
      'path' => '@organisms/by-author/rich-text.twig',
      'data' => [
        'richText' => [
          'rteElements' => [
            [
              'path' => '@atoms/11-text/paragraph.twig',
              'data' => [
                'paragraph' => [
                  'text' => $entity->{$fields['field']}->value,
                ],
              ],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Returns the variables structure required to render form downloads.
   *
   * @param object $entity
   *   The object that contains the field.
   * @param array $options
   *   An array of options.
   * @param array $cache_tags
   *   The array of cache_tags sent in the node render array.
   *
   * @see @organisms/by-author/form-download.twig
   *
   * @return array
   *   Returns a structured array.
   */
  public static function prepareFormDownloads($entity, array $options = [], array &$cache_tags = []) {
    $downloadLinks = [];
    $options += ['maxItems' => NULL];

    $map = [
      'downloads' => [
        'field_downloads',
        'field_section_downloads',
        'field_liststaticdoc_item',
      ],
      'link' => [
        'field_section_links',
        'field_listitemlink_item',
      ],
    ];

    // Determines which field names to use from the map.
    $fields = Helper::getMappedFields($entity, $map);
    // A count of the number of items we have displayed.
    $num_items = 0;

    // Roll up our items.
    if (array_key_exists('link', $fields)) {
      foreach ($entity->{$fields['link']} as $item) {
        // Stop adding items to the array if we have hit the limit.
        if ($options['maxItems'] && ($num_items >= $options['maxItems'])) {
          break;
        }
        $downloadLink = Molecules::prepareDownloadLink($item, $options);
        // prepareDownloadLink returns [] if there is no binary file attached to a file / media entity.
        if (!empty($downloadLink)) {
          $downloadLinks[] = $downloadLink;
          // Remove any duplicate downloads links along the way.
          $duplicates_removed = Helper::removeArrayDuplicates($downloadLinks);
          if (count($duplicates_removed) != count($downloadLinks)) {
            $downloadLinks = $duplicates_removed;
          }
          else {
            $num_items++;
          }
        }
      }
    }

    if (!empty($fields['downloads']) && Helper::isFieldPopulated($entity, $fields['downloads'])) {
      foreach ($entity->{$fields['downloads']}->referencedEntities() as $downloadEntity) {
        if (($options['maxItems'] != NULL) && ($num_items >= $options['maxItems'])) {
          break;
        }
        $downloadLink = Molecules::prepareDownloadLink($downloadEntity, $options);
        $cache_tags = array_merge($cache_tags, $downloadEntity->getCacheTags());

        // prepareDownloadLink returns [] if there is no binary file attached to a file / media entity.
        if (!empty($downloadLink)) {
          $downloadLinks[] = $downloadLink;
          // Remove any duplicate downloads links along the way.
          $duplicates_removed = Helper::removeArrayDuplicates($downloadLinks);
          if (count($duplicates_removed) != count($downloadLinks)) {
            $downloadLinks = $duplicates_removed;
          }
          else {
            $num_items++;
          }
        }
      }
    }

    $heading = isset($options['heading']) ? Helper::buildHeading($options['heading']) : [];

    // Sets 'more' link, if available.
    $more = [];
    if (!empty($options['more'])) {
      $more = $options['more'];
    }

    return array_merge($heading, ['downloadLinks' => $downloadLinks], ['more' => $more]);
  }

  /**
   * Returns the variables structure required to render tabularData.
   *
   * @param object $entity
   *   The object that contains the fields.
   * @param array $options
   *   This is an array of field names and other static options.
   * @param array &$cache_tags
   *   The array of cache_tags sent in the node render array.
   *
   * @see @organisms/by-author/tabular-data.twig
   *
   * @return array
   *   Returns a structured array.
   */
  public static function prepareTabularData($entity, array $options, array &$cache_tags = []) {
    $tabularData = [];
    $head = [];
    $items = [];

    // Creates a map of fields on the parent entity.
    $map = [
      'fees' => ['field_how_to_ref_fees'],
      'description' => ['field_how_to_fee_description'],
    ];

    // Creates a map of fields that are on the entity.
    $map_ref = [
      'name' => ['field_fee_name'],
      'fee' => ['field_fee_fee'],
      'unit' => ['field_fee_unit'],
    ];

    // Determines which field names to use from the map.
    $fields = Helper::getMappedFields($entity, $map);

    foreach ($map_ref as $indexFieldName => $fieldName) {
      $head['rows']['headings']['cells'][] = [
        'heading' => TRUE,
        'text' => ucfirst($indexFieldName),
      ];
    }

    foreach ($entity->get($fields['fees'])->referencedEntities() as $index => $feeEntity) {
      $cache_tags = array_merge($cache_tags, $feeEntity->getCacheTags());

      // Determines which fieldnames to use from the map.
      $field = Helper::getMappedFields($feeEntity, $map_ref);
      // Set our dollar sign.
      setlocale(LC_MONETARY, 'en_US.UTF-8');

      foreach ($map_ref as $indexFieldName => $fieldName) {
        $val = Helper::fieldValue($feeEntity, $field[$indexFieldName]);

        if ($indexFieldName === 'fee') {
          // NOTE: Our fee field has been of type plain text so we have data in all sorts of form, like
          //
          // - "For single use $7 and for seasonal pass $20"
          // - 2498
          // - "$25"
          // - "11.50"
          // - "10 dollars only"
          //
          // Our goal is to display fees as "$ x,xxx.yy" style output when possible.
          // 1. Just get the float value from all kinds of fee input (i.e. $val)
          $float_val = floatval($val);
          // 2. If the extracted float has a different numeric value, our input is a string
          // which we need to display as is. If they have the same value we do our formatting.
          if ($val == $float_val) {
            // 3. PHP does type juggling in the check above, so we also have to
            // compare trimmed lengths before formatting.
            // Eg: All three of these evaluate to true, but we must format only the last two
            // - ("10 dollars only" == 10)
            // - ("11.50" == 11.5)
            // - (2489 == 2489)
            if (strlen($val) == strlen($float_val) || strlen(rtrim(rtrim($val, "0"), ".")) == strlen($float_val)) {
              // 4. Our goal is to not show decimal values unless they were
              // explicitly added, in which case we want to format them to two decimal places.
              if (floor($float_val) == $float_val) {
                $val = "$" . number_format($float_val, 0);
              }
              else {
                $val = "$" . number_format($float_val, 2);
              }
            }
          }
          // 5. From this point on $val is the desired fee value to be displayed.
        }

        $items[$index]['rows'][$index]['cells'][] = [
          'heading' => FALSE,
          'text' => $val,
        ];
      }
    }

    // Only render heading and table column headings when there is table data.
    if (!empty($items)) {
      $heading = Helper::buildHeading($options['heading']);
    }

    // Prepare description elements.
    // No heading for fee description.
    $descriptionOptions = [
      'description' => [
        'property' => '',
      ],
    ];

    if (Helper::isFieldPopulated($entity, 'field_how_to_fee_description')) {
      $heading = Helper::buildHeading($options['heading']);
      $description = array_merge($descriptionOptions, [
        'description' => [
          'rteElements' => [
            Atoms::prepareTextField($entity, 'field_how_to_fee_description'),
          ],
        ],
      ]);
    }
    else {
      $description = [];
    };

    $table = [];
    if (!empty($items)) {
      $table = ['table' => ['head' => $head, 'bodies' => $items]];
    }
    $tabularData = array_merge($heading, $description, $table);

    return $tabularData;
  }

  /**
   * Returns the variables structure required to render actionActivities.
   *
   * @param object $entities
   *   An EntityReferenceRevisionsFieldItemList that contains the entities.
   *
   * @see @organisms/by-author/image-promos.twig
   *
   * @return array
   *   Returns a structured array.
   */
  public static function prepareImagePromos($entities) {
    $items = [];

    // Activities section.
    foreach ($entities as $entity) {
      $activityEntity = $entity->entity;

      // Creates a map of fields that are on the entitiy.
      $map = [
        'image' => ['field_image'],
        'title' => ['field_title'],
        'lede' => ['field_lede', 'field_teaser'],
        'link' => ['field_ref_location_details_page'],
      ];

      // Determines which fieldnames to use from the map.
      $fields = Helper::getMappedFields($activityEntity, $map);

      $items[] = Molecules::prepareImagePromo($activityEntity, $fields, []);
    }

    return ['items' => $items];
  }

  /**
   * Returns the variables structure required to render steps ordered.
   *
   * @param object $entity
   *   The object that contains the fields.
   * @param array $options
   *   This is an array of field names.
   *
   * @see @organisms/by-author/steps-ordered.twig
   *
   * @return array
   *   Returns a structured array.
   */
  public static function prepareStepsOrdered($entity, array $options = []) {
    $steps = [];

    // Creates a map of fields on the parent entity.
    $map = [
      'reference' => ['field_action_step_numbered_items', 'field_how_to_next_steps'],
    ];

    // Determines which fieldnames to use from the map.
    $fields = Helper::getMappedFields($entity, $map);

    // Retrieves the referenced field from the entity.
    $items = Helper::getReferencedEntitiesFromField($entity, $fields['reference']);

    // Creates a map of fields that are on the referenced entitiy.
    $referenced_fields_map = [
      'title' => ['field_title', 'field_next_step_title'],
      'content' => ['field_content', 'field_next_step_details'],
      'downloads' => ['field_next_step_downloads'],
      'more_link' => ['field_next_step_link'],
    ];

    // Determines the fieldsnames to use on the refrenced entity.
    $referenced_fields = Helper::getMappedReferenceFields($items, $referenced_fields_map);

    // Roll up our action steps.
    if (!empty($items)) {
      foreach ($items as $id => $item) {
        // Set up action step options.
        $step_options = [
          'accordion' => FALSE,
          'expanded' => TRUE,
        ];

        $steps[] = Molecules::prepareActionStep($item, $referenced_fields, $step_options);
      }
    }

    // Build either sidebar or comp heading based on heading type option.
    $heading = [];
    if (isset($options['heading']['type'])) {
      $heading = Helper::buildHeading($options['heading']);
    }

    return [
      'steps' => $steps,
    ] + $heading;
  }

  /**
   * Returns the variables structure required to render a content eyebrow.
   *
   * @param array $options
   *   The object that contains static data, widgets, and optional content.
   *
   * @see @organisms/by-template/content-eyebrow.twig
   *
   * @return array
   *   Returns an array of items.
   *    "contentEyebrow": [
   *      "hideBorder": false,
   *      "headerTags": [
   *         "label": "More about:",
   *         "taxonomyTerms": [[
   *            "href": "#",
   *            "text": "Term 1"
   *          ], [
   *            "href": "#",
   *            "text": "Term 2"
   *          ]]
   *       ],
   *      "socialLinks": [
   *        "label": "Share:",
   *        "items": [[
   *          "altText": "Follow us on Facebook",
   *          "href": "#",
   *          "icon": "facebook",
   *          "linkType": "facebook"
   *        ],]
   *          "altText": "Follow us on Twitter",
   *          "href": "#",
   *          "icon": "twitter",
   *          "linkType": "twitter"
   *        ],]
   *          "altText": "Follow us on LinkedIn",
   *          "href": "#",
   *          "icon": "linkedin",
   *          "linkType": "linkedin"
   *        ]]
   *      ]
   *    ]
   */
  public static function prepareContentEyebrow(array $options) {

    // Create the contentEyebrow data structure.
    $contentEyebrow = [
      'hideBorder' => array_key_exists('hideBorder', $options) ? $options['hideBorder'] : FALSE,
      'headerTags' => array_key_exists('headerTags', $options) ? $options['headerTags'] : NULL,
      'socialLinks' => array_key_exists('socialLinks', $options) ? $options['socialLinks'] : NULL,
    ];

    return $contentEyebrow;
  }

  /**
   * Returns the variables structure required to render a relationship indicator.
   *
   * @param array $options
   *   The object that contains static data, widgets, and optional content.
   *
   * @see @molecules/relationship-indicator.twig
   */
  public static function prepareRelationshipIndicatorPrimary(array $options) {

    // Create the relationship indicator primary term data structure.
    $relationshipIndicatorPrimary = [
      'tags' => array_key_exists('tags', $options) ? $options['tags'] : NULL,
    ];

    return $relationshipIndicatorPrimary;
  }

  /**
   * Returns the variables structure required to render steps unordered.
   *
   * @param object $entity
   *   The object that contains the fields.
   * @param array $options
   *   This is an array of field names.
   *
   * @see @organisms/by-author/steps-unordered.twig
   *
   * @return array
   *   Returns a structured array.
   */
  public static function prepareStepsUnordered($entity, array $options = []) {
    $steps = [];

    // Creates a map of fields on the parent entity.
    $map = [
      'reference' => ['field_how_to_methods_5'],
    ];

    // Determines which field names to use from the map.
    $fields = Helper::getMappedFields($entity, $map);

    // Retrieves the referenced field from the entity.
    $items = Helper::getReferencedEntitiesFromField($entity, $fields['reference']);

    // Creates a map of fields that are on the referenced entitiy.
    $referenced_fields_map = [
      'title' => ['field_method_type'],
      'content' => ['field_method_details'],
    ];

    // Determines the field names to use on the referenced entity.
    $referenced_fields = Helper::getMappedReferenceFields($items, $referenced_fields_map);

    // Map method types to icon names.
    $icon_map = [
      'online' => 'laptop',
      'phone' => 'phone',
      'mail' => 'mail',
      'fax' => 'fax-icon',
      'in person' => 'profile',
      'text' => 'message',
    ];

    // Roll up our action steps.
    if (!empty($items)) {
      foreach ($items as $id => $item) {
        // Get the icon path for the given method.
        $title = $item->get($referenced_fields['title'])->value;
        $icon_path = array_key_exists($title, $icon_map) ? $icon_map[$title] : NULL;

        // Set up action step options.
        $step_options = [
          'icon_path' => $icon_path,
          'accordion' => TRUE,
          'expanded' => FALSE,
          'label' => "Expand {$title} step.",
        ];

        $steps[] = Molecules::prepareActionStep($item, $referenced_fields, $step_options);
      }
    }

    // Build either sidebar or comp heading based on heading type option.
    $heading = [];
    if (isset($options['heading']['type'])) {
      $heading = Helper::buildHeading($options['heading']);
    }

    return [
      'steps' => $steps,
    ] + $heading;
  }

  /**
   * Helper function to prepare sidebar theme events.
   *
   * @param \Drupal\node\Entity\Node $entity
   *   Node object.
   * @param array $options
   *   This is an array of options.
   * @param array $heading_options
   *   If passed, will use heading with options used.
   * @param array &$cache_tags
   *   The array of node cache tags.
   *
   * @return array
   *   Theme preprocess array.
   */
  public static function prepareSidebarEvents(Node $entity, array $options, array $heading_options = [], array &$cache_tags = []) {
    $return_array = [];
    $options['sidebarDate'] = TRUE;

    // Prepare data for upcoming events.
    // Call a helper function to populate any relevent reference data.
    /** @var \Drupal\mass_content\EventManager $eventManager */
    $eventManager = \Drupal::service('mass_content.event_manager');
    $events = $eventManager->getUpcoming($entity, 3);
    $event_data = Helper::prepareEvents($events, $options);
    // Determine if there are past events related to this entity.
    $is_past_events = $eventManager->hasPast($entity);

    if (!empty($event_data)) {
      if (array_key_exists('more_link_text', $options) && ((count($event_data) > 2 || $is_past_events))) {
        $return_array['more'] = Helper::prepareMoreLink($entity, ['text' => $options['more_link_text']]);
      }
      // Note: The event-listing.twig check for 'grid' only checks via ternary
      // for a value, so the 'grid' array key cannot exist. Even setting it to
      // 'false' triggers the check to be evaulated as true.
      if (!empty($heading_options)) {
        // ::buildHeading returns a keyed array of 'type' in options
        // or compHeading as a default.
        // At this point $return_array is null, so array_merge is unecessary,
        // however it allows future updates to apply before the header.
        $return_array = array_merge(Helper::buildHeading($heading_options), $return_array);
      }
      else {
        $return_array['sidebarHeading']['title'] = t('Upcoming Events');
      }
      $return_array['events'] = array_splice($event_data, 0, 2);
    }
    else {
      // If there are no upcoming but past events, add see past events link to sidebar listing.
      if ($is_past_events) {
        $return_array['sidebarHeading']['title'] = t('Upcoming Events');
        $return_array['emptyText'] = t('No upcoming events scheduled');
        $return_array['pastMore'] = [
          "text" => t('See past events'),
          "href" => \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $entity->id()) . '/events/past',
          "chevron" => TRUE,
        ];
      }
    }
    return $return_array;
  }

  /**
   * Returns the variables structure required to render Expandable Content.
   *
   * @param object $entities
   *   The object that contains the fields.
   * @param array $options
   *   An array containing options.
   * @param array $field_map
   *   An optional array of fields.
   * @param array $cache_tags
   *   The array of cache_tags sent in the node render array.
   *
   * @see @organsms/by-template/table-of-contents-hierarchy.twig
   *
   * @return array
   *   Returns structured array.
   */
  public static function prepareExpandableContent($entities, array $options = [], array $field_map = NULL, array &$cache_tags = []) {
    $sections = [];
    $fields = [];

    if ($field_map) {
      $fields = Helper::getMappedFields($entities, $field_map);
    }

    // Logic for topic_cards related field.
    if (isset($fields['topic_cards'])) {
      // Loop through the Link Groups.
      foreach ($entities->{$fields['topic_cards']} as $key => $item) {
        $url = $item->getUrl();
        $link = Helper::separatedLink($item);
        // Create an item link.
        $sections[$key] = [
          "text" => $link['text'],
          "href" => $link['url'],
        ];
        // Load up our entity if internal.
        if ($url->isExternal() == FALSE && $url->isRouted() == TRUE && method_exists($url, 'getRouteParameters')) {
          $params = $url->getRouteParameters();
          $entity_type = key($params);
          $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($params[$entity_type]);

          // If the entity is a topic_page, include Link Groups and links.
          if (!empty($entity) && $entity->bundle() == 'topic_page') {
            $cache_tags = Cache::mergeTags($entity->getCacheTags(), $cache_tags);
            $topic_heading = [
              'text' => trim($sections[$key]['text']) ?: $entity->label(),
            ];
            $link_items = [];
            // Loop through the Topic Link Groups.
            foreach ($entity->field_topic_content_cards as $topic_group) {
              $cache_tags = Cache::mergeTags($topic_group->entity->getCacheTags(), $cache_tags);

              // Initialize the heading and link arrays for this Link Group.
              $topic_category_heading = [];
              $topic_links = [];
              // If a category is set, create a heading.
              $topic_title = Helper::fieldFullView($topic_group->entity, 'field_content_card_category');
              if (!empty($topic_title)) {
                $topic_category_heading = [
                  'title' => $topic_title,
                ];
              }
              // Loop through the links and create an array of link data.
              foreach ($topic_group->entity->{$fields['topic_cards']} as $topic_group_item) {
                $topic_link = Helper::separatedLink($topic_group_item);
                $topic_links[] = [
                  "text" => $topic_link['text'],
                  "href" => $topic_link['url'],
                ];
              }
              // Create the subItems array if there is a topic category heading.
              if (!empty($topic_category_heading)) {
                $link_items[] = array_merge($topic_category_heading, ['subItems' => $topic_links]);
              }
              else {
                $link_items += $topic_links;
              }
            }
            // Add the section with topic heading and linkItems.
            if (!empty($link_items)) {
              $sections[$key] = array_merge($topic_heading, ['linkItems' => $link_items]);
            }
          }
        }
      }
    }
    // Set the section heading.
    $heading = $options['categoryTitle'] ?? '';
    // Return the section content array.
    return [
      'content' => [
        'title' => [
          'text' => $heading,
          'colored' => FALSE,
        ],
        'background' => 'none',
        'sections' => $sections,
      ],
    ];
  }

}
