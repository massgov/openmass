<?php

namespace Drupal\mass_formatters\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Markup;
use Drupal\image\Plugin\Field\FieldFormatter\ImageUrlFormatter;

/**
 * Plugin implementation of the image_safe_string_url formatter.
 *
 * @FieldFormatter(
 *   id = "image_safe_string_url",
 *   label = @Translation("Image Safe String URL"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class ImageSafeStringUrlFormatter extends ImageUrlFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    foreach ($elements as &$element) {
      $element['#markup'] = Markup::create($element['#markup']);
    }
    return $elements;
  }

}
