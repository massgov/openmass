<?php

namespace Drupal\mass_map\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\mass_map\MapLocationFetcher;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\mayflower\Helper;
use Drupal\node\Entity\Node;

/**
 * Class MapController.
 *
 * @package Drupal\mass_map\Controller
 */
class MapController extends ControllerBase {

  /**
   * Content for the map pages.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node which references locations.
   *
   * @return array
   *   Render array that returns a list of locations.
   */
  public function content(NodeInterface $node) {

    // Get locations referenced from the given node.
    $node_title = $node->label();

    // Set appropriate page title.
    if ($node->getType() == "location") {
      $page_title = $this->t('Other locations related to @title', ['@title' => $node_title]);
    }
    else {
      $page_title = $node_title . ' Locations';
    }

    $pageHeader = [
      'title' => $page_title,
      'divider' => FALSE,
      'headerTags' => [
        'label' => 'More about:',
        'taxonomyTerms' => [
          [
            'href' => $node->toUrl(),
            'text' => $node_title,
          ],
        ],
      ],
    ];

    $ids = $this->getMapLocationIds($node);

    $location_fetcher = new MapLocationFetcher();
    // Use the ids to get location info.
    $locations = $location_fetcher->getLocations($ids);

    // If there are no locations for the parent node, return a 404.
    if (empty($locations['imagePromos']['items'])) {
      throw new NotFoundHttpException();
    }

    return [
      '#theme' => 'map_page',
      '#pageHeader' => $pageHeader,
      '#locationListing' => $locations,
      '#attached' => [
        'library' => [
          'mass_map/mass-map-page-renderer',
          'mass_map/mass-google-map-apis',
        ],
        'drupalSettings' => [
          'locations' => $locations,
        ],
      ],
    ];
  }

  /**
   * Title in <head> for the map pages.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node which references locations.
   *
   * @return array
   *   Render array that returns a list of locations.
   */
  public function title(NodeInterface $node) {
    $node_title = $node->label();
    $page_title = $node_title . ' Locations';

    return $page_title;
  }

  /**
   * Get a list of location ids from the parent id.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node that contains a map row paragraph or location nids.
   *
   * @return array
   *   A list of location node ids.
   */
  private function getMapLocationIds(NodeInterface $node) {
    $locationIds = $this->getLocationIds($node);

    // Extract location info from right rail layout.
    if ($node->getType() == 'action') {
      $locationIds = $this->getActionLocationIds($node);
    }
    // Extract location info from stacked layout.
    if ($node->getType() == 'stacked_layout') {
      $locationIds = $this->getStackedLayoutLocationIds($node);
    }
    return $locationIds;
  }

  /**
   * Get location ids from Right Rail node.
   *
   * @param object $node
   *   Right Rail node.
   *
   * @return array
   *   And array containing location ids.
   */
  private function getActionLocationIds($node) {
    $locationIds = [];

    // Get map row out of the details paragraph.
    if (!empty($node->field_action_details)) {
      foreach ($node->field_action_details as $detail_id) {
        $detail = Paragraph::load($detail_id->target_id);
        if ($detail->getType() == 'map_row') {
          foreach ($detail->field_map_locations as $location) {
            $locationIds[] = $location->target_id;
          }
          break;
        }
      }
    }
    return $locationIds;
  }

  /**
   * Get location ids from Stacked Layout node.
   *
   * @param object $node
   *   Stacked Layout node.
   *
   * @return array
   *   And array containing location ids.
   */
  private function getStackedLayoutLocationIds($node) {
    $locationIds = [];

    if (!empty($node->field_bands)) {
      foreach ($node->field_bands as $band_id) {
        // Search the main bands field a map row.
        $band = Paragraph::load($band_id->target_id);
        if (!empty($band->field_main)) {
          foreach ($band->field_main as $band_main_id) {
            $band_main = Paragraph::load($band_main_id->target_id);
            if ($band_main->getType() == 'map_row') {
              foreach ($band_main->field_map_locations as $location) {
                $locationIds[] = $location->target_id;
              }
              break;
            }
          }
        }
      }
    }
    return $locationIds;
  }

  /**
   * Get location ids from node.
   *
   * @param object $node
   *   Current node.
   *
   * @return array
   *   And array containing location ids.
   */
  private function getLocationIds($node) {
    $locationIds = [];

    // Possible location fields for each bundle (org, service page)
    $map = [
      'mappedLocations' => [
        'field_related_locations',
        'field_org_ref_locations',
        'field_service_ref_locations',
      ],
    ];

    // Determines which field names to use from the map.
    $fields = [];
    // Set node as the default data source.
    $source = $node;
    // Org page locations moved to a nested paragraph.
    if ($node->getType() == 'org_page') {
      if (!$node->field_organization_sections->isEmpty()) {
        // Get the entity type manager service.
        $this->entityTypeManager();
        // Get the sections field value.
        $field_organization_sections = $node->get('field_organization_sections')
          ->getValue();
        // Loop through the organization sections.
        foreach ($field_organization_sections as $section_item) {
          // Set properties to use for loading the section paragraph.
          $section_properties = [
            'id' => $section_item['target_id'],
            'revision_id' => $section_item['target_revision_id'],
          ];
          // Load the section paragraph.
          $section_paragraph = current($this->entityTypeManager->getStorage('paragraph')
            ->loadByProperties($section_properties));
          // If the content field is not empty, proceed.
          if ($section_paragraph->field_section_long_form_content ?? FALSE && !$section_paragraph->field_section_long_form_content->isEmpty()) {
            // Get the content field value.
            $field_section_long_form_content = $section_paragraph->get('field_section_long_form_content')
              ->getValue();
            // Loop through the content paragraphs.
            foreach ($field_section_long_form_content as $content_item) {
              // Set properties to use for loading the content paragraph.
              $content_properties = [
                'id' => $content_item['target_id'],
                'revision_id' => $content_item['target_revision_id'],
              ];
              // Load the content paragraph.
              $content_paragraph = current($this->entityTypeManager->getStorage('paragraph')
                ->loadByProperties($content_properties));
              // If the content paragraph is an org_locations paragraph, get data
              // from that entity instead of the node.
              if ($content_paragraph instanceof Paragraph && $content_paragraph->bundle() == 'org_locations') {
                // Set the data source as the paragraph.
                $source = $content_paragraph;
                $fields = Helper::getMappedFields($source, $map);
                break 2;
              }
            }
          }
        }
      }
    }
    else {
      // Set the data source as the node.
      $fields = Helper::getMappedFields($source, $map);
    }

    if (!empty($fields)) {
      if (array_key_exists('mappedLocations', $fields) && Helper::isFieldPopulated($source, $fields['mappedLocations'])) {
        foreach ($source->{$fields['mappedLocations']} as $entity) {
          // Verify locations have addresses.
          $nid = $entity->target_id;
          $location_node = Node::load($nid);
          $contact_info_id = $location_node->field_ref_contact_info_1->target_id;
          $contact_info_node = Node::load($contact_info_id);
          // Only display locations with addresses.
          if (!$contact_info_node->field_ref_address->isEmpty()) {
            $locationIds[] = $entity->target_id;
          }
        }
      }
    }

    return $locationIds;
  }

}
