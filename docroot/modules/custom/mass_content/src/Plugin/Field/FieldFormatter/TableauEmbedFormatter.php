<?php

namespace Drupal\mass_content\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;

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
    $elements = [];

    $paragraph = $items->getEntity();

    // Determine the embed type from the paragraph field.
    $embed_type = $paragraph->get('field_tableau_embed_type')->value ?? 'default';
    $token_url = $paragraph->get('field_tableau_url_token')->uri ?? NULL;

    foreach ($items as $delta => $item) {
      $id = bin2hex(random_bytes(8));
      $url = $this->buildUrl($item);

      $elements[$delta] = [
        '#theme' => 'mass_content_tableau_embed',
        '#url' => $url,
        '#randId' => $id,
        '#embed_type' => $embed_type,
        '#token_url' => ($embed_type === 'connected_apps' && $token_url) ? $token_url : NULL,
      ];
    }

    return $elements;
  }

}
