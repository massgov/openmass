<?php

namespace Drupal\mass_styles\Plugin\views\style;

use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Style plugin for the cards view.
 *
 * @ViewsStyle(
 *   id = "maSection3upContainer",
 *   title = @Translation("MA Section 3up Container"),
 *   help = @Translation("Display Topics on Section page as 3up"),
 *   theme = "ma_section3up_container_style",
 *   display_types = {"normal"}
 * )
 */
class MaSection3upContainer extends StylePluginBase {

  /**
   * Specifies if the plugin uses row plugins.
   *
   * @var bool
   */
  protected $usesRowPlugin = TRUE;

}
