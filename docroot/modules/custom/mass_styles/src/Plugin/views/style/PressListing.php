<?php

namespace Drupal\mass_styles\Plugin\views\style;

use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Style plugin for a view to render as mayflower Image Promos.
 *
 * @ViewsStyle(
 *   id = "press_listing",
 *   title = @Translation("Press listing"),
 *   help = @Translation("Displays row content in Press listing component"),
 *   theme = "press_listing_style",
 *   display_types = {"normal"}
 * )
 */
class PressListing extends StylePluginBase {

  /**
   * Specifies if the plugin uses row plugins.
   *
   * @var bool
   */
  protected $usesRowPlugin = TRUE;

}
