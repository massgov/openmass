<?php

namespace Drupal\mass_formatters\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'text_length' formatter.
 *
 * @FieldFormatter(
 *   id = "text_length",
 *   label = @Translation("Text Length"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "text_with_summary",
 *     "string",
 *     "string_long",
 *   }
 * )
 */
class TextLength extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    // The ProcessedText element already handles cache context & tag bubbling.
    // @see \Drupal\filter\Element\ProcessedText::preRenderText()
    foreach ($items as $delta => $item) {

      $length = 0;
      if (!empty($item->value)) {
        $length = mb_strlen($item->value);
      }

      $elements[$delta] = [
        '#type' => 'processed_text',
        '#text' => $length,
        '#format' => $item->format,
        '#langcode' => $item->getLangcode(),
      ];
    }

    return $elements;
  }

}
