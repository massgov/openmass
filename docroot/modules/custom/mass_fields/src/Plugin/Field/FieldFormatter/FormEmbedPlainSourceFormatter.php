<?php

namespace Drupal\mass_fields\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'form_embed_plain_source' formatter.
 *
 * @FieldFormatter(
 *   id = "form_embed_plain_source",
 *   label = @Translation("Form Embed plain source field"),
 *   field_types = {
 *     "form_embed"
 *   }
 * )
 */
class FormEmbedPlainSourceFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      if (preg_match('/<noscript>\s*<a\s+href=["\']?([^"\'>]+)["\']?/', $item->value, $matches)) {
        if ($matches[1]) {
          $element[$delta] = [
            '#type' => 'plain_text',
            '#plain_text' => $matches[1],
          ];
        }
      }
    }

    return $element;
  }

}
