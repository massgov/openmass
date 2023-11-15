<?php

namespace Drupal\mayflower;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\crop\Entity\Crop;
use Drupal\image\Entity\ImageStyle;
use Drupal\mayflower\Prepare\Atoms;
use Drupal\mayflower\Prepare\Molecules;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Drupal\views\ViewExecutable;

/**
 * Provides mayflower prepare functions with helper functions.
 */
class Helper {

  /**
   * Helper function to determine whether or not a field is populated.
   *
   * @param object $entity
   *   Entity that contains the field to be checked.
   * @param string $field_name
   *   The name of the field to be checked.
   *
   * @return bool
   *   Whether or not a field is populated.
   */
  public static function isFieldPopulated($entity, $field_name) {
    if (!method_exists($entity, 'hasField')) {
      return FALSE;
    }

    $is_populated = FALSE;

    $has_field = $entity->hasField($field_name);

    if ($has_field) {
      $field = $entity->get($field_name);
      if ($field->count() > 0) {
        $is_populated = TRUE;
      }
    }

    return $is_populated;
  }

  /**
   * Helper function to retrieve the fields needed by the pattern.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   *   The entity which contains the fields.
   * @param array $map
   *   The array which contains all potentially used fields.
   *
   * @return array
   *   The array which contains the fields used by this pattern.
   */
  public static function getMappedFields(ContentEntityBase $entity, array $map) {
    $fields = [];
    // Determines which field names to use from the map.
    // @todo refactor to make use array functions (map, filter, reduce)
    foreach ($map as $id => $key) {
      foreach ($key as $field) {
        if ($entity->hasField($field)) {
          $fields[$id] = $field;
        }
      }
    }

    return $fields;
  }

  /**
   * Provide the URL of an image.
   *
   * @param object $entity
   *   The node with the field on it.
   * @param string $style_name
   *   (Optional) The name of an image style.
   * @param string $field
   *   The name of an the image field.
   * @param int $delta
   *   (Optional) the delta of the image field to display, defaults to 0.
   *
   * @return string
   *   The URL to the styled image, or to the original image if the style
   *   does not exist.
   */
  public static function getFieldImageUrl($entity, $style_name = NULL, $field = NULL, $delta = 0) {
    $url = '';

    $fields = $entity->get($field);

    if ($fields) {
      /** @var \Drupal\file\Entity\File[] $images */
      $images = $fields->referencedEntities();
    }

    if (!empty($images)) {
      $image = $images[$delta];

      if (!empty($style_name) && ($style = ImageStyle::load($style_name))) {
        $uri = $image->getFileUri();
        switch ($entity->bundle()) {
          case 'org_page':
          case 'info_details':
            if ($style_name == 'action_banner_large_focal_point') {
              $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager');
              $style_uri = $style->buildUri($uri);
              $url = $style->buildUrl($uri);
              if (!file_exists($style_uri) || !$stream_wrapper_manager->isValidUri($style_uri)) {
                // Fallback style if the focal point style is not generated.
                $style = ImageStyle::load('action_banner_large');
                $url = $style->buildUrl($uri);
              }
            }
            break;

          case 'service_page':
            if ($style_name == '800x400_fp') {
              $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager');
              $style_uri = $style->buildUri($uri);
              $url = $style->buildUrl($uri);
              if (!file_exists($style_uri) || !$stream_wrapper_manager->isValidUri($style_uri)) {
                // Fallback style if the focal point style is not generated.
                $style->createDerivative($uri, $style_uri);
                $url = $style->buildUrl($uri);
              }
            }
            break;

          default:
            $url = $style->buildUrl($uri);
            break;
        }
      }
      else {
        $url = $image->createFileUrl();
      }
    }
    return $url;
  }

  /**
   * Provide the focal point of an image as a percentage.
   *
   * If no focal point exists then 50% / 50% is provided.
   *
   * @param object $entity
   *   The node with the field on it.
   * @param string $field
   *   The name of an the image field.
   * @param int $delta
   *   (Optional) the delta of the image field to display, defaults to 0.
   *
   * @return array
   *   Array with two keys (x, y) with coordinates as string values e.g.
   *   ['x' => '50%', 'y' => '50%']
   */
  public static function getFieldImageFocalPoint($entity, $field = NULL, $delta = 0) {
    $fields = $entity->get($field);
    $image = $fields->get($delta);
    $file = $fields->referencedEntities();
    if (!empty($image)) {
      $crop_type = \Drupal::config('focal_point.settings')->get('crop_type');
      $file_uri = $file[$delta]->getFileUri();
      if (Crop::cropExists($file_uri, $crop_type)) {
        $crop = Crop::findCrop($file_uri, $crop_type);
        if (!empty($crop) && !empty($crop->position() && is_array($crop->position()))) {
          $crop_position = $crop->position();
          if (isset($crop_position['x']) && isset($crop_position['y'])) {

            if (isset($fields->get($delta)->width) && isset($fields->get($delta)->height)) {
              $width = round(($crop_position['x'] / $fields->get($delta)->width) * 100, 2);
              $height = round(($crop_position['y'] / $fields->get($delta)->height) * 100, 2);
            }
            else {
              $img = \Drupal::service('image.factory')->get($image->entity->getFileUri());
              if ($img->isValid()) {
                $width = round(($crop_position['x'] / $img->getWidth()) * 100, 2);
                $height = round(($crop_position['y'] / $img->getHeight()) * 100, 2);
              }
            }
            return ['x' => "$width%", 'y' => "$height%"];
          }
        }
      }
    }

    return ['x' => '50%', 'y' => '50%'];
  }

  /**
   * Helper function to provide url of an entity based on presence of a field.
   *
   * @param object $entity
   *   Entity object that contains the external url field.
   * @param string $external_url_link_field
   *   The name of the field.
   *
   * @return array
   *   Array that contains url and type (external, internal).
   */
  public static function getEntityUrl($entity, $external_url_link_field = '') {
    if ((!empty($external_url_link_field)) && (Helper::isFieldPopulated($entity, $external_url_link_field))) {
      // External URL field exists & is populated so get its URL + type.
      $links = Helper::separatedLinks($entity, $external_url_link_field);
      // @todo update mayflower_separated_links so we don't need [0]
      return $links[0];
    }
    else {
      // External URL field is non-existent or empty, get Node path alias.
      $url = $entity->toURL();
      return [
        'href' => $url->toString(),
        'type' => 'internal',
      ];
    }
  }

  /**
   * Helper function to provide separated link parts for multiple links.
   *
   * @param object $entity
   *   Entity object that contains the link field.
   * @param string $field
   *   The name of the field.
   * @param array $options
   *   An array of options.
   *
   * @return array
   *   Array that contains title, url and type (external, internal).
   */
  public static function separatedLinks($entity, $field, array $options = []) {
    $items = [];
    // Track the count to enforce a max number of items.
    $item_count = 0;
    // Check if the target field is entity reference, else assume link field.
    if (Helper::isEntityReferenceField($entity, $field)) {
      // Retrieves the entities referenced from the entity field.
      $entities = Helper::getReferencedEntitiesFromField($entity, $field);

      foreach ($entities as $entity) {
        if (!empty($options['maxItems']) && ($item_count >= $options['maxItems'])) {
          break;
        }
        $text = $entity->get('title')->value;
        // Use date as title if specified.
        if (!empty($options['useDate']) && !empty($entity->{$options['useDate']['fieldDate']})) {
          $date = \Drupal::service('date.formatter')->format(strtotime($entity->{$options['useDate']['fieldDate']}->value), 'custom', 'l, F d, Y');
          $text = $date;
          if (!empty($options['useDate']) && !empty($entity->{$options['useDate']['fieldTime']})) {
            $time = $entity->{$options['useDate']['fieldTime']}->value;
            $text = $date . ', ' . $time;
          }
        }
        $items[] = [
          'url' => $entity->toURL()->toString(),
          'href' => $entity->toURL()->toString(),
          'text' => $text,
        ];
        $item_count++;
      }
    }
    else {
      $links = $entity->get($field);

      foreach ($links as $link) {
        if (!empty($options['maxItems']) && ($item_count >= $options['maxItems'])) {
          break;
        }
        $items[] = Helper::separatedLink($link, $options);
        $item_count++;
      }
    }

    return $items;
  }

  /**
   * Helper function to provide separated link parts.
   *
   * @param object $link
   *   The link object.
   * @param array $options
   *   An array of options.
   *
   * @return array
   *   Array that contains title, url and type (external, internal).
   */
  public static function separatedLink($link, array $options = []) {
    $url = $link->getUrl();
    // Quick fix for https://jira.mass.gov/browse/DP-10001.
    // @todo Fix this properly.
    $markup = $link->computed_title;
    $text = is_string($markup) ? $markup : $markup['#markup'];
    $date = '';
    $content_type = '';

    if (empty($options['useEyebrow'])) {
      $options['useEyebrow'] = [];
    }

    if ($linkedEntity = Helper::entityFromUrl($url)) {
      $content_type = $linkedEntity->bundle();
      if (Helper::isFieldPopulated($linkedEntity, 'field_date_published')) {
        $date = Helper::fieldFullView($linkedEntity, 'field_date_published');
      }
    }

    return [
      'image' => '',
      'text' => $text,
      'type' => (UrlHelper::isExternal($url->toString())) ? 'external' : 'internal',
      'href' => $url->toString(),
      'url' => $url->toString(),
      'label' => '',
      'eyebrow' => in_array($content_type, $options['useEyebrow']) ? $options['category'] : '',
      'date' => !empty($date) ? $date : '',
    ];
  }

  /**
   * Helper function to provide separated email link parts.
   *
   * @param object $entity
   *   Entity object that contains the link field.
   * @param string $field_name
   *   The name of the field.
   *
   * @return array
   *   Array that contains title, url.
   */
  public static function separatedEmailLink($entity, $field_name) {
    $link = $entity->get($field_name);

    return [
      'text' => $link->value,
      'href' => $link->value,
    ];
  }

  /**
   * Helper function to provide entity from URI.
   *
   * @param string $uri
   *   String that contains the uri.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Entity that contains fields, or NULL if not an entity URI.
   */
  public static function entityFromUri($uri) {
    return self::entityFromUrl(Url::fromUri($uri));
  }

  /**
   * Helper function to provide entity from Url object.
   *
   * @param \Drupal\Core\Url $url
   *   The Url to check for an entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   An entity object, or NULL if not an entity Url.
   */
  public static function entityFromUrl(Url $url) {
    // Urls that aren't routed can't have an entity, so skip further processing.
    // Also skip URLs that claim to be routed, but don't have any valid internal
    // path.  This can happen if the user enters only a fragment.
    if ($url->isRouted() && $url->getInternalPath()) {
      $params = $url->getRouteParameters();
      $entity_type = key($params);
      $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($params[$entity_type]);
      return $entity;
    }
    return NULL;
  }

  /**
   * Helper function to provide render array for a field.
   *
   * @param object $entity
   *   Entity that contains the field to render.
   * @param string $field_name
   *   The name of the field.
   *
   * @return array
   *   Returns the full render array of the field.
   */
  public static function fieldFullView($entity, $field_name) {
    $field_array = [];
    $field = $entity->get($field_name);

    if ($field->count() > 0) {
      $field_array = $field->first()->view('full');
    }

    return $field_array;
  }

  /**
   * Helper function to get the value of a referenced field.
   *
   * @param object $field
   *   Send a field object.
   * @param string $referenced_field
   *   Name of the referenced field.
   *
   * @return array
   *   The value of the referenced field.
   */
  public static function getReferenceField($field, $referenced_field) {
    if (method_exists($field, 'referencedEntities') && isset($field->referencedEntities()[0]) && $field->referencedEntities()[0]->hasField($referenced_field)) {
      return $field->referencedEntities()[0]->get($referenced_field)->value;
    }
    return FALSE;
  }

  /**
   * Helper function to retrieve the entities referenced from the entity field.
   *
   * @param object $entity
   *   The entity which contains the reference field.
   * @param string $reference_field
   *   The name of the entity reference field.
   *
   * @return array
   *   The array which contains the entities referenced by the field.
   */
  public static function getReferencedEntitiesFromField($entity, $reference_field) {
    // Retrieves the featured actions referenced from the entity field.
    $field = $entity->get($reference_field);
    $referenced_items = [];
    if ($field->count() > 0) {
      $referenced_items = $field->referencedEntities();
    }

    return $referenced_items;
  }

  /**
   * Helper function to retrieve the entities referenced from a section field.
   *
   * @param object $entity
   *   The entity which contains the nested reference field.
   * @param array $reference_fields
   *   An array of the nested entity reference field names.
   *
   * @return array
   *   The array which contains the entities referenced by the field.
   */
  public static function getReferencedEntitiesFromSectionField($entity, array $reference_fields) {
    // Get the sections field.
    $sections_field = array_shift($reference_fields);
    // Get the sections content field.
    $sections_field_content = array_shift($reference_fields);
    // Get the nested reference field.
    $reference_field = end($reference_fields);
    if ($entity->hasField($sections_field)) {
      $sections_field_list = $entity->get($sections_field);
      foreach ($sections_field_list as $section_field_value) {
        if (!$section_field_entity = $section_field_value->entity) {
          continue;
        }
        if ($section_field_entity->hasField($sections_field_content)) {
          $sections_field_content_list = $section_field_entity->get($sections_field_content);
          foreach ($sections_field_content_list as $sections_field_content_value) {
            $sections_field_content_entity = $sections_field_content_value->entity;
            if ($sections_field_content_entity) {
              if ($sections_field_content_entity->hasField($reference_field)) {
                return self::getReferencedEntitiesFromField($sections_field_content_entity, $reference_field);
              }
            }
          }
        }
      }
    }
    // Return an empty array if the reference field was never found.
    return [];
  }

  /**
   * Helper function to provide a value for a field.
   *
   * @param object $entity
   *   Entity that contains the field to render.
   * @param string $field_name
   *   The name of the field.
   *
   * @return string
   *   Returns the value of the field.
   */
  public static function fieldValue($entity, $field_name) {
    $value = '';
    $field = $entity->get($field_name);
    if ($field->count() > 0) {
      $value = $field->first()->value;
    }
    return $value;
  }

  /**
   * Helper function to find the field names to use on the entity.
   *
   * @param array $referenced_entities
   *   Array that contains the featured/all actions referenced entities.
   * @param array $referenced_fields_map
   *   The array which contains the list of possible fields from the
   *   referenced entities.
   *
   * @return array
   *   The array which contains the list of necessary fields from the
   *   referenced entities.
   */
  public static function getMappedReferenceFields(array $referenced_entities, array $referenced_fields_map) {
    // @todo determine if this can be combined with mayflower_get_mapped_fields
    $referenced_fields = [];
    // Determines the field names to use on the referenced entity.
    foreach ($referenced_fields_map as $id => $key) {
      foreach ($key as $field) {
        if (isset($referenced_entities[0]) && $referenced_entities[0]->hasField($field)) {
          $referenced_fields[$id] = $field;
        }
      }
    }

    return $referenced_fields;
  }

  /**
   * Helper function to populate a featured/links property of action finder.
   *
   * @param array $referenced_entities
   *   Array that contains the featured/all actions referenced entities.
   * @param array $referenced_fields
   *   The array which contains the list of necessary fields from the
   *   referenced entities.
   *
   * @return array
   *   The variable structure for the featured/links property.
   */
  public static function populateActionFinderLinks(array $referenced_entities, array $referenced_fields) {
    // Populate links array.
    $links = [];
    if (!empty($referenced_entities)) {
      foreach ($referenced_entities as $item) {

        // Get the image, if there is one.
        $image = "";
        if (!empty($referenced_fields['image'])) {
          $is_image_field_populated = Helper::isFieldPopulated($item, $referenced_fields['image']);
          if ($is_image_field_populated) {
            $image = Helper::getFieldImageUrl($item, 'thumbnail_130x160', $referenced_fields['image']);
          }
        }

        // Get url + type from node external url field if exists and is
        // populated, otherwise from node url.
        $ext_url_field = "";
        if (!empty($referenced_fields['external'])) {
          $ext_url_field = $referenced_fields['external'];
        }
        $url = Helper::getEntityUrl($item, $ext_url_field);

        $links[] = [
          'image' => $image,
          'text' => $item->{$referenced_fields['text']}->value,
          'type' => $url['type'],
          'href' => $url['href'],
        ];
      }
    }

    return $links;
  }

  /**
   * Check for icon twig templates.
   *
   * Note: With the implementation of icon(), icon twig templates are not
   * necessary anymore for non user added icons.  The icon twig templates
   * don't exist in mayflower.
   *
   * @param string $icon
   *   The icon to render.
   *
   * @return string
   *   The path to the icon twig file.
   *
   *   With the implementation of icon(), the icon name for non user added
   *   icons.
   */
  public static function getIconPath($icon) {
    $expected_path = sprintf('%s/assets/images/icons/%s.svg', mayflower_get_path(), strtolower($icon));

    if (file_exists($expected_path)) {
      return strtolower($icon);
    }

    return 'doc-generic';
  }

  /**
   * Check for icon twig templates for a taxonomy term.
   *
   * @param \Drupal\taxonomy\Entity\Term $term
   *   The taxonomy term to get the icon path for.
   *
   * @return string
   *   The path to the icon twig file.
   */
  public static function getIconPathFromIconTerm(Term $term) {
    if ($term->bundle() == 'icon') {
      $icon_name = $term->get('field_sprite_name');
      if ($icon_name->count() > 0) {
        $icon = $icon_name->getValue();
        $icon = $icon[0]['value'];
        return $icon;
      }
    }
    return 'doc-generic';
  }

  /**
   * Returns the current path alias.
   */
  public static function getCurrentPathAlias() {
    $path = \Drupal::service('path.current')->getPath();
    return \Drupal::service('path_alias.manager')->getAliasByPath($path);
  }

  /**
   * Returns the first line or paragraph of a string of text or raw HTML.
   *
   * @param string $string
   *   The text string or rawHTML string to be parsed.
   *
   * @return string
   *   The first line or paragraph of a string of text or raw HTML.
   */
  public static function getFirstParagraph($string) {
    if (!is_string($string)) {
      return FALSE;
    }

    // Get only the first html paragraph from the field value.
    if (preg_match("%(<p[^>]*>.*?</p>)%i", $string, $matches)) {
      return strip_tags($matches[1]);
    }

    // Get only the first plain text line.
    $plain_text_lines = preg_split("/\"\/\r\n|\r|\n\/\"/", $string);
    if ($plain_text_lines !== FALSE) {
      return $plain_text_lines[0];
    }

    return FALSE;
  }

  /**
   * Return a subset of a contactList data structure with primary phone/online.
   *
   * @param array $contact_list
   *   A contactList: see @organisms/by-author/contact-list.
   *
   * @return array
   *   A contactList array with only phone and online info for primary contact.
   */
  public static function getPrimaryContactPhoneOnlineContactList(array $contact_list) {
    // Build sidebar.contactList, a subset of pageContent.ContactList.
    $sidebar_contact = [];
    // Get the first contact point only.
    $contact_list['contacts'] = array_slice($contact_list['contacts'], 0, 1);
    // Make the contact render for sidebar contact list.
    $contact_list['contacts'][0]['accordion'] = FALSE;
    // Remove the address, fax, in person contact groups.
    foreach ($contact_list['contacts'][0]['groups'] as $key => $item) {
      if (in_array($item['name'], ['Address', 'Fax', 'In Person'])) {
        unset($contact_list['contacts'][0]['groups'][$key]);
      }
    }
    // If contact groups remain, they are online / phone, assign to return var.
    if (count($contact_list['contacts'][0]['groups']) > 0) {
      $sidebar_contact = $contact_list;
    }
    return $sidebar_contact;
  }

  /**
   * Remove the appended cache string from a URL.
   *
   * @param string $url
   *   The URL to be sanitized.
   * @param string $cacheString
   *   The string to be sanitized from the URL.
   *
   * @return string
   *   The sanitized URL.
   */
  public static function sanitizeUrlCacheString($url, $cacheString) {
    if (!is_string($url) || !is_string($cacheString)) {
      return FALSE;
    }

    $pos = strpos($url, $cacheString);
    if ($pos !== FALSE) {
      $url = substr($url, 0, $pos);
    }

    return $url;
  }

  /**
   * Supplements page meta data from metatags.
   *
   * @param array $metadata
   *   Array of pageMetaData used by templates/includes/page-meta.html.twig.
   * @param array $map
   *   Array that maps metatags to page_meta_data keys in form tag=>key.
   *   Defaults to 'siteDescription'=>'siteDescription'.
   * @param string $meta_area
   *   The part of the metadata attachments array to search in.
   *   Defaults to html_head.
   *
   * @return array
   *   The array with appended metatag values.
   */
  public static function addMetatagData(array $metadata, array $map = [], $meta_area = 'html_head') {
    // Code largely copied from metatag.module/metatag_preprocess_html()
    if (!function_exists('metatag_is_current_route_supported') || !metatag_is_current_route_supported()) {
      return $metadata;
    }

    if (empty($map)) {
      $map = [
        'siteDescription' => 'siteDescription',
        'description' => 'description',
      ];
    }

    $attachments = &drupal_static('metatag_attachments');
    if (is_null($attachments)) {
      $attachments = metatag_get_tags_from_route();
    }

    if (!$attachments || empty($attachments['#attached'][$meta_area])) {
      return $metadata;
    }

    foreach ($attachments['#attached'][$meta_area] as $metatag) {
      $tag_name = $metatag[1];
      if (isset($map[$tag_name])) {
        // It's safe to access the value directly because it was already
        // processed in MetatagManager::generateElements().
        $metadata[$map[$tag_name]] = $metatag[0]['#attributes']['content'];
      }
    }

    return $metadata;
  }

  /**
   * Returns the center lat/lng of a map.
   *
   * @param array $data
   *   Array of coords for each marker.
   *
   * @return array
   *   Return an array with center lat and lng.
   */
  public static function getCenterFromDegrees(array $data) {
    if (!is_array($data)) {
      return FALSE;
    }
    $num_coords = count($data);
    $iX = 0.0;
    $iY = 0.0;
    $iZ = 0.0;
    foreach ($data as $coord) {
      $lat = $coord[0] * pi() / 180;
      $lon = $coord[1] * pi() / 180;
      $a = cos($lat) * cos($lon);
      $b = cos($lat) * sin($lon);
      $c = sin($lat);
      $iX += $a;
      $iY += $b;
      $iZ += $c;
    }
    $iX /= $num_coords;
    $iY /= $num_coords;
    $iZ /= $num_coords;
    $lon = atan2($iY, $iX);
    $hyp = sqrt($iX * $iX + $iY * $iY);
    $lat = atan2($iZ, $hyp);
    return [
      $lat * 180 / pi(),
      $lon * 180 / pi(),
    ];
  }

  /**
   * Helper function to prepare the Hours.
   *
   * @param object $entity
   *   The object that contains the necessary fields.
   * @param array $options
   *   The object that contains static data and other options..
   *
   * @return array
   *   Return an array of items that contain:
   *    "office hours": {
   *    }
   */

  /**
   * Helper function to build Hours section.
   *
   * @param object $hours
   *   Send a field object.
   * @param string $title
   *   Which generates the heading before a section.
   * @param string $titleContext
   *   Add supplemental information to the heading.
   *
   * @return array
   *   Return structured array.
   */
  public static function buildHours($hours, $title, $titleContext = NULL) {
    $rteElements = [];

    // Hours section.
    foreach ($hours as $index => $hour) {
      $entity = $hour->entity;

      if (!method_exists($entity, 'hasField')) {
        return FALSE;
      }

      // Creates a map of fields that are on the entitiy.
      $map = [
        'label' => ['field_label', 'field_hours_group_title'],
        'time' => ['field_time_frame', 'field_hours_structured'],
        'hour' => ['field_hours', 'field_hours_description'],
        'description' => ['field_hours_description'],
      ];

      // Determines which fieldnames to use from the map.
      $field = Helper::getMappedFields($entity, $map);
      $hours_render_array = $entity->field_hours_structured->view('full');

      if (!empty($field['label']) && Helper::isFieldPopulated($entity, $field['label'])) {
        $rteElements[] = [
          'path' => '@atoms/04-headings/heading-5.twig',
          'data' => [
            'heading5' => [
              'text' => Helper::fieldValue($entity, $field['label']),
            ],
          ],
        ];
      }

      if (!empty($field['description']) && Helper::isFieldPopulated($entity, $field['description'])) {
        $rteElements[] = [
          'path' => '@atoms/11-text/paragraph.twig',
          'data' => [
            'paragraph' => [
              'text' => Helper::fieldFullView($entity, $field['description']),
            ],
          ],
        ];
      }
      else {
        $rteElements[] = [
          'path' => '@atoms/11-text/raw-html.twig',
          'data' => [
            'rawHtml' => [
              'content' => $hours_render_array,
            ],
          ],
        ];
      }
    }

    return [
      'compHeading' => $title,
      'titleContext' => $titleContext,
      'into' => '',
      'id' => Helper::createIdTitle($title),
      'path' => '@organisms/by-author/rich-text.twig',
      'data' => [
        'richText' => [
          'property' => '',
          'rteElements' => $rteElements,
        ],
      ],
    ];
  }

  /**
   * Returns the variables structure required for richText.
   *
   * @param array $elements
   *   An array of elements.
   *
   * @see @organsms/by-author/rich-text.twig
   *
   * @return array
   *   'path' => '@organisms/by-author/rich-text.twig',
   *     'data' => [
   *       'richText' => [
   *         'rteElements' => array of rteElements,
   *       ],
   *     ],
   *   ]
   */
  public static function prepareRichTextElements(array $elements) {
    if (!is_array($elements)) {
      return [];
    }
    return [
      'path' => '@organisms/by-author/rich-text.twig',
      'data' => [
        'richText' => [
          'rteElements' => $elements,
        ],
      ],
    ];
  }

  /**
   * Return the data structure for a link based on a given entity.
   *
   * @param object $entity
   *   The object for which we want the link href, type, text, image, and label.
   *
   * @return array
   *   A an array with structure for illustrated, callout, or decorative link:
   *    [
   *      'href' => 'http://path/to/entity',
   *      'type' => 'internal' || 'external',
   *      'title' => 'My Entity Title',
   *      'image' => 'http://path/to/image',
   *      'label' => 'Guide:', etc.
   *    ]
   */
  public static function createLinkFromEntity($entity) {

    // Creates a map of fields that are on the referenced entity.
    $map = [
      'image' => ['field_photo', 'field_guide_page_bg_wide'],
      'text' => ['title', 'field_title'],
      'external' => ['field_external_url'],
      'href' => [],
    ];

    // Determines which field names to use from the map.
    $fields = Helper::getMappedFields($entity, $map);

    // Get the image, if there is one.
    $image = "";
    if (!empty($fields['image'])) {
      $is_image_field_populated = Helper::isFieldPopulated($entity, $fields['image']);
      if ($is_image_field_populated) {
        $image = Helper::getFieldImageUrl($entity, 'thumbnail_130x160', $fields['image']);
      }
    }

    // Get url + type from node external url field if exists and is
    // populated, otherwise from node url.
    $ext_url_field = "";
    if (!empty($fields['external'])) {
      $ext_url_field = $fields['external'];
    }
    $url = Helper::getEntityUrl($entity, $ext_url_field);

    $label = '';
    if ($entity->getType() === 'guide_page' || $entity->getType() === 'stacked_layout') {
      $label = "Guide:";
    }

    $link = [
      'image' => $image,
      'text' => !empty($fields['text']) ? Helper::fieldValue($entity, $fields['text']) : '',
      'type' => $url['type'],
      'href' => $url['href'],
      'label' => $label,
    ];

    return $link;
  }

  /**
   * Return an array of links: decorative, callout or illustrated.
   *
   * @param object $entity
   *   The entity which contains the entity reference or link field.
   * @param object $field
   *   The field which contains or refers to the link information.
   * @param array $options
   *   Array of options.
   * @param array &$cache_tags
   *   The array of node cache tags.
   *
   * @return array
   *   Returns an array with the following structure:
   *    [
   *      [
   *        'href' => 'http://path/to/entity',
   *        'type' => 'internal' || 'external',
   *        'title' => 'My Entity Title',
   *        'image' => 'http://path/to/image',
   *        'label' => 'Guide:', etc.
   *      ], ...
   *    ]
   */
  public static function createIllustratedOrCalloutLinks($entity, $field, array $options = [], array &$cache_tags = []) {
    $items = [];

    // Check if the target field is entity reference, else assume link field.
    if (Helper::isEntityReferenceField($entity, $field)) {
      // Retrieves the entities referenced from the entity field.
      $referenced_entities = Helper::getReferencedEntitiesFromField($entity, $field);

      $item_count = 0;
      foreach ($referenced_entities as $referenced_entity) {
        if (!empty($options['maxItems']) && ($item_count >= $options['maxItems'])) {
          break;
        }
        if ($referenced_entity->isPublished()) {
          $cache_tags = array_merge($cache_tags, $referenced_entity->getCacheTags());
          $items[] = Helper::createLinkFromEntity($referenced_entity, $options);
          $item_count++;
        }
      }

      return $items;
    }
    else {
      $links = Helper::separatedLinks($entity, $field, $options);
    }

    return $links;
  }

  /**
   * Returns SEO title.
   *
   * @param string $title
   *   An element.
   *
   * @return string
   *   A well processed link id.
   */
  public static function createIdTitle(string $title) {
    $replaced = preg_replace('/-+/', '-', preg_replace('/[^\wáéíóú]/', '-', $title));
    return strtolower($replaced);
  }

  /**
   * Return pageHeader.optionalContents structure populated with contactUs.
   *
   * @param object $entity
   *   The entity which contains the entity reference or link field.
   * @param object $field
   *   The field which contains or refers to the link information.
   * @param array $options
   *   An array of options for header contact.
   * @param array &$cache_tags
   *   The array of node cache tags.
   *
   * @see @molecules/contact-us.twig
   * @see @organisms/page-header/page-header.twig
   *
   * @return array
   *   Returns an array with the following structure:
   *   [ [
   *       'path' => '@molecules/contact-us.twig',
   *       'data' => [
   *         'contactUs' => [ contact us data structure ]
   *       ],
   *     ], ... ]
   */
  public static function buildPageHeaderOptionalContentsContactUs($entity, $field, array $options = [], array &$cache_tags = []) {
    $optionalContentsContactUs = [];
    $contactUs = [];
    $contact_items = Helper::getReferencedEntitiesFromField($entity, $field);

    if (!empty($contact_items)) {
      foreach ($contact_items as $contact_item) {
        // Get entity cache tags.
        $cache_tags = array_merge($cache_tags, $contact_item->getCacheTags());

        $contactUs = Molecules::prepareContactUs($contact_item, $options);
      }

      $optionalContentsContactUs[] = [
        'path' => '@molecules/contact-us.twig',
        'data' => ['contactUs' => $contactUs],
      ];
    }

    return $optionalContentsContactUs;
  }

  /**
   * Return pageHeader.optionalContents structure populated with contactUs from address paragraph field.
   *
   * @param object $entity
   *   The entity which contains the address paragraph.
   * @param object $field
   *   The field which refers to the address paragraph.
   * @param array $options
   *   An array of options for header contact.
   *
   * @see @molecules/contact-us.twig
   * @see @organisms/page-header/page-header.twig
   *
   * @return array
   *   Returns an array with the following structure:
   *   [ [
   *       'path' => '@molecules/contact-us.twig',
   *       'data' => [
   *         'contactUs' => [ contact us data structure ]
   *       ],
   *     ], ... ]
   */
  public static function buildPageHeaderOptionalContentsContactUsAddress($entity, $field, array $options = []) {
    $optionalContentsContactUs = [];
    $contactUs = [];
    $contact_items = Helper::getReferencedEntitiesFromField($entity, $field);
    if (!empty($contact_items)) {
      foreach ($contact_items as $contact_item) {
        $contactUs = Molecules::prepareAddress($contact_item, $options);
      }

      $optionalContentsContactUs[] = [
        'path' => '@molecules/contact-us.twig',
        'data' => ['contactUs' => $contactUs],
      ];
    }

    return $optionalContentsContactUs;
  }

  /**
   * Return structure necessary for either sidebar or comp heading.
   *
   * @param array $options
   *   Array of options.
   *   [
   *     'type' => 'compHeading' || 'sidebarHeading' || 'coloredHeading',
   *     'title' => 'My title text' / required,
   *     'sub' => [required if TRUE],
   *     'centered' => [required if TRUE],
   *     'color' => [required if 'green', 'yellow'],
   *   ].
   *
   * @return array
   *   The data structure for either comp or sidebar heading.
   */
  public static function buildHeading(array $options) {
    $heading_type = isset($options['type']) ? $options['type'] : 'compHeading';

    $heading = [
      $heading_type => [
        'title' => isset($options['title']) ? $options['title'] : '',
        'titleContext' => isset($options['titleContext']) ? $options['titleContext'] : '',
        'text' => isset($options['title']) ? $options['title'] : '',
        'sub' => isset($options['sub']) ? $options['sub'] : FALSE,
        'color' => isset($options['color']) ? $options['color'] : '',
        'id' => isset($options['title']) ? Helper::createIdTitle($options['title']) : '',
        'centered' => isset($options['centered']) ? $options['centered'] : '',
        'level' => isset($options['level']) ? $options['level'] : '',
      ],
    ];

    return $heading;
  }

  /**
   * Returns whether or not the entity's field is an entity reference field.
   *
   * @param object $entity
   *   The object that has the field which we are checking.
   * @param array $fields
   *   Array of parent field map.
   *   [
   *     'issuer' => '[field_name'],
   *   ].
   * @param array $ref_map
   *   Array of referenced field map.
   *   [
   *     'display_name' => '[field_name'],
   *     'title' => '[field_name'],
   *   ].
   *
   * @return array
   *   The data structure for a row in a source listing table.
   */
  public static function issuerListingTable($entity, array $fields, array $ref_map) {
    $issuers = [];
    if (empty($fields) || !array_key_exists('issuer', $fields) || !$entity instanceof ContentEntityInterface) {
      return $issuers;
    }
    if (empty($ref_map) || !array_key_exists('display_name', $ref_map) || !array_key_exists('title', $ref_map)) {
      return $issuers;
    }
    $issuers_entities = Helper::getReferencedEntitiesFromField($entity, $fields['issuer']);
    foreach ($issuers_entities as $issuer_entity) {
      if ($issuer_entity instanceof ContentEntityInterface) {
        $issuer_name = '';
        $issuer_title = '';

        $issuer_fields = Helper::getMappedFields($issuer_entity, $ref_map);

        if (!empty($issuer_fields['display_name']) && Helper::isFieldPopulated($issuer_entity, $issuer_fields['display_name'])) {
          $issuer_name = Helper::fieldValue($issuer_entity, $issuer_fields['display_name']);
        }

        if (!empty($issuer_fields['title']) && Helper::isFieldPopulated($issuer_entity, $issuer_fields['title'])) {
          $issuer_title = Helper::fieldValue($issuer_entity, $issuer_fields['title']);
        }

        $issuers[] = t("@display_name@title", ['@display_name' => $issuer_name, '@title' => !empty($issuer_title) ? ', ' . $issuer_title : '']);
      }
    }

    return $issuers;
  }

  /**
   * Returns whether or not the entity's field is an entity reference field.
   *
   * @param object $entity
   *   The object that has the field which we are checking.
   * @param string $field
   *   The name of the field which we are checking.
   *
   * @return bool
   *   Whether or not this entity's field is entity reference.
   */
  public static function isEntityReferenceField($entity, $field) {
    return $entity->getFieldDefinition($field)->getType() === 'entity_reference';
  }

  /**
   * Returns the field type of a given field.
   *
   * @param object $entity
   *   The object that has the field which we are checking.
   * @param string $field
   *   The name of the field which we are checking.
   *
   * @return string
   *   Return the type of field.
   */
  public static function fieldType($entity, $field) {
    return $entity->getFieldDefinition($field)->getType();
  }

  /**
   * Returns a formatted address from entity.
   *
   * @param object $addressEntity
   *   The object that contains the field.
   * @param array $options
   *   An array of options.
   *
   * @return string
   *   A flattened string of address info.
   */
  public static function formatAddress($addressEntity, array $options = []) {
    $address = '';

    if (isset($addressEntity[0])) {
      // Add address module fields.
      $address = !empty($addressEntity[0]->address_line1) ? $addressEntity[0]->address_line1 . ', ' : '';
      // If we're in the sidebar add a newline.
      if (!empty($options['sidebar'])) {
        $address .= PHP_EOL;
      }
      $address .= !empty($addressEntity[0]->address_line2) ? $addressEntity[0]->address_line2 . ', ' : '';
      // If we're in the sidebar add a newline.
      if (!empty($options['sidebar'])) {
        $address .= PHP_EOL;
      }
      $address .= !empty($addressEntity[0]->locality) ? $addressEntity[0]->locality : '';
      $address .= !empty($addressEntity[0]->administrative_area) ? ', ' . $addressEntity[0]->administrative_area : '';
      $address .= !empty($addressEntity[0]->postal_code) ? ' ' . $addressEntity[0]->postal_code : '';
    }

    return $address;
  }

  /**
   * Return structure necessary for sources in listing table row.
   *
   * @param object $entity
   *   Entity object.
   * @param array $fields
   *   Array of field map.
   *   [
   *     'sources' => '[field_name'],
   *   ].
   * @param array $options
   *   Array of options to pass to function.
   *   [
   *     'label' => '[label'],
   *   ].
   *
   * @return array
   *   The data structure for a row in a source listing table.
   */
  public static function rowListingTable($entity, array $fields, array $options) {
    $label = '';
    $moreLabel = '';
    $lessLabel = '';
    $visibleItems = '';

    if (array_key_exists('label', $options)) {
      $label = $options['label'];
    }

    if (array_key_exists('moreLabel', $options)) {
      $moreLabel = t($options['moreLabel']);
    }

    if (array_key_exists('lessLabel', $options)) {
      $lessLabel = t($options['lessLabel']);
    }

    if (array_key_exists('visibleItems', $options)) {
      $visibleItems = t($options['visibleItems']);
    }

    $row = [];
    if (empty($fields) || !array_key_exists('sources', $fields)) {
      return $row;
    }
    if (!empty($entity) && $entity instanceof ContentEntityInterface) {
      $links = Helper::dataFromLinkField($entity, ['link' => $fields['sources']]);

      if (!empty($links)) {
        foreach ($links as $link) {
          $sources[] = Link::fromTextAndUrl($link['title'], $link['url'])->toString();
        }

        $row = [
          'label' => t("@label@colon", ['@label' => $label, '@colon' => ':']),
          'text' => nl2br(implode("\n", $sources)),
          'moreLabel' => $moreLabel,
          'lessLabel' => $lessLabel,
          'visibleItems' => $visibleItems,
        ];
      }
    }
    return $row;
  }

  /**
   * Return structure necessary for sources in listing table row.
   *
   * @param object $entity
   *   Entity object.
   * @param array $field
   *   Array of field map.
   *   [
   *     'link' => '[field_name'],
   *   ].
   *
   * @return array
   *   An array of links with url, title and location.
   */
  public static function dataFromLinkField($entity, array $field) {
    $links = [];
    if (empty($field) || !array_key_exists('link', $field) || !$entity instanceof ContentEntityInterface) {
      return $links;
    }

    if (Helper::isFieldPopulated($entity, $field['link'])) {
      foreach ($entity->{$field['link']} as $id => $link) {
        if (!$link->isExternal() && $entity_ref = Helper::entityFromUrl($link->getUrl())) {
          if ($entity_ref instanceof ContentEntityInterface) {
            $title = $link->computed_title;

            if (empty($title)) {
              $title = $entity_ref->label();
            }

            $links[] = [
              'url' => $entity_ref->toURL(),
              'title' => $title,
              'location' => 'internal',
            ];
          }
        }
        else {
          $links[] = [
            'url' => $link->getUrl(),
            'title' => $link->computed_title,
            'location' => 'external',
          ];
        }
      }
    }
    return $links;
  }

  /**
   * Helper function to return event data.
   *
   * @param \Drupal\mass_content\Entity\Bundle\node\EventBundle[] $events
   *   The event nodes.
   * @param array $options
   *   Display options to use in render.
   *
   * @return array
   *   Events.
   */
  public static function prepareEvents(array $events = [], array $options = []) {
    // Setup an array for the expected return data.
    $return_data = [];

    foreach ($events as $key => $event_entity) {

      // Create the map of all possible field names to use.
      $event_map = [
        'date' => ['field_event_date'],
        'time' => ['field_event_time'],
        'lede' => ['field_event_lede'],
        'tags' => ['field_event_ref_parents', 'field_event_ref_event_2'],
        'links' => ['field_event_links'],
        'image' => ['field_event_image'],
        'logo' => ['field_event_logo'],
        'downloads' => ['field_event_ref_downloads'],
        'contact' => ['field_event_contact_general'],
        'address_type' => ['field_event_address_type'],
      ];

      // Get the address type.
      $address_type = '';
      if (!$event_entity->getAddressType()->isEmpty()) {
        $address_type = $event_entity->getAddressType()->getValue();
        $address_type = reset($address_type);
        $address_type = $address_type['value'];
      }

      // Build contact us optional contents from appropriate field.
      if (Helper::isFieldPopulated($event_entity, 'field_event_ref_contact') && (empty($address_type) || ($address_type == 'contact info'))) {
        $event_map['location'] = ['field_event_ref_contact'];
      }
      if (Helper::isFieldPopulated($event_entity, 'field_event_ref_unique_address') && ($address_type == 'unique')) {
        $event_map['location'] = ['field_event_ref_unique_address'];
      }

      // Determines which field names to use from the map.
      $fields = Helper::getMappedFields($event_entity, $event_map);
      $return_data[] = Molecules::prepareEventTeaser($event_entity, $fields, $options);
    }
    return $return_data;
  }

  /**
   * Get date properties in mayflower format.
   *
   * @param string $timestamp
   *   Timestamp from field.
   * @param array $options
   *   Options to change date format.
   *
   * @return array
   *   Date properties in Mayflower format, ie Datetime, date and time.
   */
  public static function getMayflowerDate($timestamp, array $options = []) {
    // @see https://medium.com/massdigital/mass-gov-style-guide-e677ec4c0c57
    $dateTime = Helper::getDate($timestamp);
    $date_format = isset($options['date_format']) ? $options['date_format'] : 'l, F j, Y';
    $date = $dateTime->format($date_format);
    $time = strtr($dateTime->format('g:i a'), [
      'am' => 'a.m.',
      'pm' => 'p.m.',
      ':00' => '',
    ]);
    return [$dateTime, $date, $time];
  }

  /**
   * Get Date object.
   *
   * @param string $timestamp
   *   The timestamp.
   *
   * @return \DateTime
   *   Date object.
   */
  public static function getDate($timestamp) {
    // @todo DP-7978 determine why we aren't using the drupal date service
    // 'date.formatter'.
    $dateTime = new \DateTime($timestamp, new \DateTimeZone('UTC'));
    $timezone = 'America/New_York';
    $dateTime->setTimeZone(new \DateTimeZone($timezone));
    return $dateTime;
  }

  /**
   * Prepare a more link.
   *
   * @param \Drupal\node\Entity\Node $entity
   *   The node object.
   * @param array $options
   *   Display options to use in render.
   *
   * @return array
   *   Formatted more link.
   */
  public static function prepareMoreLink(Node $entity, array $options) {
    $text = '';

    if (!$entity instanceof ContentEntityInterface) {
      return [];
    }

    if (array_key_exists('text', $options)) {
      $text = $options['text'];
    }

    $href = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $entity->id()) . '/events';

    return [
      'href' => $href,
      'text' => $text,
      'chevron' => TRUE,
      'labelContext' => t('for the @label', ['@label' => $entity->label()]),
    ];
  }

  /**
   * Prepare video values for template.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   Video entity to show in atom template.
   * @param array $options
   *   Options to show the video (height, width and position).
   *   For example:
   *   $options = [
   *     "height" => "340",
   *     "width" => "600",
   *     "position" => "right",
   *   ].
   * @param array $cache_tags
   *   The array of cache_tags sent in the node render array.
   *
   * @return array|null
   *   Video values to be used in atom video template or NULL.
   */
  public static function getVideoAtomData(Paragraph $paragraph, array $options = [], array &$cache_tags = []) {
    if (empty($paragraph) || $paragraph->getEntityTypeId() != 'paragraph' || ($paragraph->getType() != 'video' && $paragraph->getType() != 'video_with_header' && $paragraph->getType() != 'video_with_section')) {
      return NULL;
    }

    // Inside the paragraph there are other entities as User and ParagraphsType.
    /** @var \Drupal\media\Entity\Media $entity */
    foreach ($paragraph->referencedEntities() as $entity) {
      if ($entity->getEntityTypeId() == 'media' && $entity->bundle() == 'media_video') {
        $cache_tags = array_merge($cache_tags, $entity->getCacheTags());
        return Atoms::prepareVideo($entity, $options);
      }
    }

    return NULL;
  }

  /**
   * Helper function to return unique multidim array.
   *
   * @param array $array
   *   An array with keys.
   * @param string $key
   *   The array key to look for and sort by.
   *
   * @return array
   *   A unique array with keys.
   */
  public static function uniqueMultidimArray(array $array, $key) {
    $temp_array = [];
    $i = 0;
    $key_array = [];

    foreach ($array as $val) {
      // Remove spaces.
      $val[$key] = trim($val[$key]);

      if (!in_array($val[$key], $key_array)) {
        $key_array[$i] = $val[$key];
        $temp_array[$i] = $val;
      }
      $i++;
    }
    return $temp_array;
  }

  /**
   * Helper function to return event data.
   *
   * @param \Drupal\node\Entity\Node $entity
   *   The Node object to pull event refs from.
   * @param string $field
   *   Field name.
   * @param string $id
   *   Field name of content id.
   *
   * @return array
   *   Unique array of Issuers.
   */
  public static function getIssuer(Node $entity, $field, $id) {
    $issuers = [];

    foreach ($entity->{$field} as $issuer) {
      $issuerEntity = $issuer->entity;
      $signeeOrgContentID = $issuerEntity->{$id}->value;
      $storage = \Drupal::entityTypeManager()->getStorage('node');
      $signeeOrg = $storage->load($signeeOrgContentID);

      // Only use 'Related To' links with ORG pages.
      if (!empty($signeeOrg) && $signeeOrg->getType() == 'org_page') {
        $issuers[] = [
          'href' => $signeeOrg->toURL()->toString(),
          'text' => $signeeOrg->getTitle(),
        ];
      }
    }

    if (!empty($issuers)) {
      $issuers = Helper::uniqueMultidimArray($issuers, 'text');
    }

    return $issuers;
  }

  /**
   * Helper method to de-duplicate array elements.
   *
   * @param array $array
   *   Array to be de-duplicated.
   *
   * @return array
   *   De-duplicated array.
   */
  public static function removeArrayDuplicates(array $array) {
    $result = array_map('unserialize', array_unique(array_map('serialize', $array)));
    foreach ($result as $key => $value) {
      if (is_array($value)) {
        $result[$key] = self::removeArrayDuplicates($value);
      }
    }
    return $result;
  }

  /**
   * Helper for views pager.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view object to build a page header for.
   *
   * @return array
   *   Array of elements to create the pager / results.
   */
  public static function prepareViewsPager(ViewExecutable $view) {
    // Calculates values for Results Heading molecule in Mayflower style guide.
    // @see views-view--image-promos.html.twig
    if (!empty($pager = $view->pager) && $pager instanceof Full) {
      $total_items = $pager->getTotalItems();
      $items_per_page = $pager->getItemsPerPage();
      $pager_total = $pager->getPagerTotal();
      // Adds 1 to method result, since pages are indexed from 0.
      $current_page = $pager->getCurrentPage() + 1;
      $is_last_page = ($current_page == $pager_total);
      // Calculates current item range.
      if ($is_last_page) {
        $last_page_count = $total_items % $items_per_page;
        $current_range_end = $total_items;
        $current_range_start = $current_range_end - $last_page_count + 1;
      }
      else {
        $current_range_end = $current_page * $items_per_page;
        $current_range_start = $current_range_end - $items_per_page + 1;
      }

      return [
        'numResults' => $current_range_start . '–' . $current_range_end,
        'totalResults' => $total_items,
      ];
    }

    return [];
  }

  /**
   * Helper for views header.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view object to build a page header for.
   *
   * @return array
   *   Array of elements to create the page header.
   */
  public static function prepareViewsHeader(ViewExecutable $view) {
    // Check if a NID has been passed as an argument to the view.
    if (!empty($view->argument) && !empty($view->argument['nid'])) {
      // Set subtitle of view display page.
      // @see \Drupal\node\Plugin\views\argument\Nid
      $parent_node_titles = $view->argument['nid']->titleQuery();
      $link_title = reset($parent_node_titles);
      // Uses reset to return only the one node id.
      $nid = reset($view->argument['nid']->value);
      $link = Link::createFromRoute($link_title, 'entity.node.canonical', ['node' => $nid]);
      $link_render_array = $link->toRenderable();
      $link_render_array['#prefix'] = t('For ');

      // Collates data for relationship indicator to be displayed in page header.
      return [
        'subTitle' => $link_render_array,
        'headerTags' => [
          'label' => t('Related to:'),
          'taxonomyTerms' => [
            [
              'href' => $link->getUrl()->toString(),
              'text' => $link_title,
            ],
          ],
        ],
      ];
    }

    return [];
  }

  /**
   * Return the HTML to reference an SVG.
   */
  public static function getSvgEmbed($hash) {
    return sprintf('<svg aria-hidden="true" focusable="false"><use xlink:href="#%s"></use></svg>', $hash);
  }

  /**
   * Return the HTML to SVG source.
   *
   * An icon unit is wrapped with <symbol> to add structure and semantics
   * to it, which promotes accessibility.
   *
   * <title> and <desc> tags can be added within the <symbol> for
   * accessibility, but in our case, the svg icons are decorative,
   * and they are not necessary.
   * Ones used for linked images are handled their accessibility
   * treatment with their parent <a>.
   *
   * The viewBox can be defined on the <symbol>, so you don't need to use it
   * in the markup (easier and less error prone).
   * Symbols don't display as you define them, so no need for a <defs> block.
   */
  public static function getSvgSource($hash, \DOMElement $sourceNode) {
    $symbol = $sourceNode->ownerDocument->createElementNS($sourceNode->namespaceURI, 'symbol');

    // Copy attributes from <svg> to <symbol>.
    /** @var \DOMAttr $attribute */
    foreach ($sourceNode->attributes as $attribute) {
      $symbol->setAttribute($attribute->name, $attribute->value);
    }

    // Set an explicit ID.
    $symbol->setAttribute('id', $hash);

    // Copy all child nodes from the SVG to the symbol.
    // This has to be a double loop due to an issue with DOMNodeList.
    // @see http://php.net/manual/en/domnode.appendchild.php#121829
    foreach ($sourceNode->childNodes as $node) {
      $children[] = $node;
    }

    foreach ($children as $child) {
      $symbol->appendChild($child);
    }

    return $sourceNode->ownerDocument->saveXML($symbol);
  }

  /**
   * Load a single SVG as a DOMElement.
   *
   * @return \DOMElement|null
   *   The SVG's DOMElement, or null if the SVG file was not found.
   */
  public static function getSvg($path) {
    // Make sure the file exists before trying to fetch it and parse it as an
    // XML document.
    if (!file_exists($path)) {
      trigger_error(sprintf('Not a valid file: "%s"', $path), E_USER_DEPRECATED);
      return;
    }
    // For security reasons, we don't want to allow anything but an .svg file
    // to be included this way.
    if (!pathinfo($path, PATHINFO_EXTENSION) === 'svg') {
      trigger_error(sprintf('Invalid SVG file: "%s"', $path), E_USER_WARNING);
      return;
    }
    if ($svg = file_get_contents($path)) {
      $doc = new \DOMDocument('1.0', 'UTF-8');
      if ($doc->loadXML($svg)) {
        return $doc->firstChild;
      }
      // No need to error_log here. \DomDocument will log for us.
    }

  }

  /**
   * Wrap an array of SVG strings with a div that hides them from display.
   */
  public static function wrapInlinedSvgs(array $inlineSvgs) {
    if ($inlineSvgs) {
      // All icons can be wrapped in one <svg>.
      return sprintf('<svg xmlns="http://www.w3.org/2000/svg" style="display: none">%s</svg>', implode('', $inlineSvgs));
    }
    return '';
  }

  /**
   * Wrap an array of SVG strings with a div that hides them from display.
   */
  public static function findSvg($content) {
    preg_match_all("<svg-placeholder path=\"(.*\.svg)\">", $content, $matches);

    if (!empty($matches[1])) {
      return array_unique(array_filter($matches[1]));
    }

    return [];
  }

  /**
   * Helper for retrieving parent node from nested paragraphs.
   */
  public static function getParentNode(Paragraph $paragraph): ?NodeInterface {
    $parent_entity = $paragraph->getParentEntity();
    if ($parent_entity && $parent_entity->getEntityTypeId() === 'paragraph') {
      $parent_entity = self::getParentNode($parent_entity);
    }
    return $parent_entity;
  }

  /**
   * Helper to check if paragraph is orphan.
   */
  public static function isParagraphOrphan(EntityInterface $entity) {
    if ($entity instanceof Paragraph) {
      $parent_field_name = $entity->parent_field_name->value;
      $parent = $entity->getParentEntity();
      if ($parent) {
        if ($parent->hasField($parent_field_name)) {
          if ($parent->get($parent_field_name)->isEmpty()) {
            return TRUE;
          }
          else {
            $values = [
              'target_id' => $entity->id(),
              'target_revision_id' => $entity->getRevisionId(),
            ];
            if (in_array($values, $parent->get($parent_field_name)->getValue())) {
              return self::isParagraphOrphan($parent);
            }
            else {
              return TRUE;
            }
          }
        }
      }
      else {
        return TRUE;
      }
    }
    return FALSE;
  }

}
