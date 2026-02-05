<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_body_preview' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_body_preview",
 *   label = @Translation("mg_body_preview"),
 *   description = @Translation("A preview of the page contents."),
 *   name = "mg_body_preview",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagBodyPreview extends MetaNameBase {

  use MassMetatagFallbackFieldTrait;

  /**
   * {@inheritdoc}
   */
  public function output(): array {
    $element = parent::output();

    if (empty($element)) {
      $element = [
        '#tag' => 'meta',
        '#attributes' => [
          'name' => $this->name,
          'content' => '',
        ],
      ];
    }

    $raw_preview = '';
    if (!empty($element['#attributes']['content'])) {
      $raw_preview = $element['#attributes']['content'];
    }

    // Get the value from the field.
    $value = trim($this->value());

    // If this value is null or an empty string (multiple rendered paragraphs
    // are comma-delimited, so the output of 2 rendered paragraphs will show
    // up as " , "), check and use the fallback field values if possible.
    if (!$value || preg_replace('/,|\s+/', '', $value) === '') {
      // Get the fallback field value - specifying the field type to get the
      // value for.
      $raw_preview = $this->getFallbackFieldValue('text_long');
    }

    // Trim and replace newlines with a space.
    $trimmed_preview = trim(preg_replace('/\s\s+/', ' ', $raw_preview));

    // Only use the first 300 words in the preview.
    $word_limit = 300;
    $preview_words = explode(' ', $trimmed_preview);
    if (count($preview_words) > $word_limit) {
      $trimmed_words = array_slice($preview_words, 0, $word_limit);

      // Add an ellipsis to the end of the body if we had to trim it.
      $trimmed_preview = implode(' ', $trimmed_words) . '&hellip;';
    }

    $element['#attributes']['content'] = $trimmed_preview;
    // Don't print anything if the content is empty and fallback is empty.
    if (empty($element['#attributes']['content'])) {
      return [];
    }
    return $element;
  }

}
