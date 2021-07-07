<?php

namespace Drupal\mass_caching;

use Drupal\Core\Asset\AssetCollectionRendererInterface;
use Drupal\Core\Asset\CssCollectionRenderer;

/**
 * Renders CSS assets.
 */
class MassCachingCSSCollectionRenderer extends CssCollectionRenderer implements AssetCollectionRendererInterface {

  /**
   * {@inheritdoc}
   */
  public function render(array $css_assets) {
    $elements = [];

    // A dummy query-string is added to filenames, to gain control over
    // browser-caching. The string changes on every update or full cache
    // flush, forcing browsers to load a new copy of the files, as the
    // URL changed.
    $query_string = $this->state->get('system.css_js_query_string', '0');

    // Defaults for LINK and STYLE elements.
    $link_element_defaults = [
      '#type' => 'html_tag',
      '#tag' => 'link',
      '#attributes' => [
        'rel' => 'stylesheet',
      ],
    ];

    foreach ($css_assets as $css_asset) {
      $element = $link_element_defaults;
      $element['#attributes']['media'] = $css_asset['media'];
      $element['#browsers'] = $css_asset['browsers'];

      switch ($css_asset['type']) {
        // For file items, output a LINK tag for file CSS assets.
        case 'file':
          $query_string_separator = (strpos($css_asset['data'], '?') !== FALSE) ? '&' : '?';
          $element['#attributes']['href'] = file_url_transform_relative(file_create_url($css_asset['data'])) . $query_string_separator . $query_string;
          break;

        case 'external':
          $element['#attributes']['href'] = $css_asset['data'];
          break;

        default:
          throw new \Exception('Invalid CSS asset type.');
      }

      // Merge any additional attributes.
      if (!empty($css_asset['attributes'])) {
        $element['#attributes'] += $css_asset['attributes'];
      }

      $elements[] = $element;
    }

    return $elements;
  }

}
