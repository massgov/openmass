<?php

namespace Drupal\mass_formatters\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'plain_text_first_line' formatter.
 *
 * @FieldFormatter(
 *   id = "plain_text_first_line",
 *   label = @Translation("Plain Text First Line"),
 *   field_types = {
 *     "string_long",
 *   }
 * )
 */
class PlainTextFirstLine extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    // The ProcessedText element already handles cache context & tag bubbling.
    // @see \Drupal\filter\Element\ProcessedText::preRenderText()
    foreach ($items as $delta => $item) {

      $text = "";

      // Get only the first plain text line.
      $plain_text_lines = preg_split("/\"\/\r\n|\r|\n\/\"/", $item->value);
      if ($plain_text_lines !== FALSE) {
        $text = $plain_text_lines[0];
      }

      $elements[$delta] = [
        '#type' => 'processed_text',
        '#text' => $text,
        '#format' => $item->format,
        '#langcode' => $item->getLangcode(),
      ];
    }

    return $elements;
  }

}
