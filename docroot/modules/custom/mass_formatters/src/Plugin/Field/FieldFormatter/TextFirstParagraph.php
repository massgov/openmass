<?php

namespace Drupal\mass_formatters\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'text_first_paragraph' formatter.
 *
 * @FieldFormatter(
 *   id = "text_first_paragraph",
 *   label = @Translation("Text First Paragraph"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "text_with_summary",
 *   }
 * )
 */
class TextFirstParagraph extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    // The ProcessedText element already handles cache context & tag bubbling.
    // @see \Drupal\filter\Element\ProcessedText::preRenderText()
    foreach ($items as $delta => $item) {

      $text = "";

      // Get only the first html paragraph from the field value.
      if (preg_match("%(<p[^>]*>.*?</p>)%i", $item->value, $matches)) {
        $text = $matches[1];
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
