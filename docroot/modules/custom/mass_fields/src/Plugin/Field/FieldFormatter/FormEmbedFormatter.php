<?php

namespace Drupal\mass_fields\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'entity_user_access_f' formatter.
 *
 * @FieldFormatter(
 *   id = "form_embed",
 *   label = @Translation("Form Embed field"),
 *   field_types = {
 *     "form_embed"
 *   }
 * )
 */
class FormEmbedFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      $element[$delta] = [
        '#theme' => 'form_embed_formatter',
        '#type' => 'plain_text',
        '#plain_text' => $item->value,
      ];
    }

    return $element;
  }

}
