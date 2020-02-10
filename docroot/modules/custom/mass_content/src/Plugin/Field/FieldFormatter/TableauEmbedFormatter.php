<?php

namespace Drupal\mass_content\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;
use Drupal\mayflower\Helper;

/**
 * Plugin implementation of the 'tableau_embed' formatter.
 *
 * @FieldFormatter(
 *   id = "tableau_embed",
 *   label = @Translation("Tableau Embed"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class TableauEmbedFormatter extends LinkFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    $element = [];

    foreach ($items as $delta => $item) {

      $id = bin2hex(random_bytes(8));
      $url = $this->buildUrl($item);

      $element[$delta] = [
        '#theme' => 'mass_content_tableau_embed',
        '#url' => $url,
        '#randId' => $id,
      ];
    }

    return $element;
  }

}
