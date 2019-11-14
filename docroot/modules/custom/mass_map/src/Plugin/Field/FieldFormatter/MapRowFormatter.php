<?php

namespace Drupal\mass_map\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\mass_map\MapLocationFetcher;

/**
 * Plugin implementation of the 'Random_default' formatter.
 *
 * @FieldFormatter(
 *   id = "map_row_formatter",
 *   label = @Translation("Map Row"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class MapRowFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = t('Embed Google Map with location markers.');

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $location_ids = [];

    foreach ($items as $delta => $item) {
      $location_ids[] = $item->target_id;
    }

    $location_fetcher = new MapLocationFetcher();
    // Use the ids to get location info.
    $locations = $location_fetcher->getLocations($location_ids);

    return [
      '#theme' => 'map_row',
      '#locationListing' => $locations,
      '#attached' => [
        'library' => [
          'mass_map/mass-map-field-renderer',
          'mass_map/mass-google-map-apis',
        ],
        'drupalSettings' => [
          'locations' => $locations,
          'nodeId' => \Drupal::routeMatch()->getRawParameter('node'),
        ],
      ],
    ];
  }

}
