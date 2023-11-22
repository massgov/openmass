<?php

namespace Drupal\mass_styles\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Style plugin for a view to render as mayflower Image Promos.
 *
 * @ViewsStyle(
 *   id = "image_promos",
 *   title = @Translation("Image promos"),
 *   help = @Translation("Displays row content in ImagePromo component"),
 *   theme = "image_promo_style",
 *   display_types = {"normal"}
 * )
 */
class ImagePromos extends StylePluginBase {

  /**
   * Specifies if the plugin uses row plugins.
   *
   * @var bool
   */
  protected $usesRowPlugin = TRUE;

  /**
   * Set default options.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['type'] = ['default' => 'ref_from_node'];

    return $options;
  }

  /**
   * Build the options form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Relationship type'),
      '#options' => ['ref_from_node' => $this->t('Links or references from node'), 'ref_reverse' => $this->t('Reverse relationship')],
      '#default_value' => $this->options['type'],
    ];
  }

}
