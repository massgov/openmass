<?php

namespace Drupal\mass_fields\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * A filter that adds a test attribute to any configured HTML tags.
 *
 * @Filter(
 *   id = "mass_fields_translate_attr",
 *   title = @Translation("Add translate attribute"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 *   settings = {
 *     "tags" = {"span"},
 *   },
 *   weight = 10
 * )
 */
class FilterTranslate extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    if ($langcode !== 'en') {
      $document = Html::load($text);
      foreach ($this->settings['tags'] as $tag) {
        $tag_elements = $document->getElementsByTagName($tag);
        foreach ($tag_elements as $tag_element) {
          if (!empty($tag_element->getAttribute('lang'))) {
            $tag_element->setAttribute('translate', 'no');
          }
        }
      }
      return new FilterProcessResult(Html::serialize($document));
    }
  }

}
