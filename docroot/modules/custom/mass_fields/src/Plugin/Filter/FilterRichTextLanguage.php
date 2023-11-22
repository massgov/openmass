<?php

namespace Drupal\mass_fields\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Filters lang attributes in the rich text.
 *
 * @Filter(
 *   id = "filter_richtext_lang",
 *   title = @Translation("Filter lang attributes"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 * )
 */
class FilterRichTextLanguage extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $document = Html::load($text);
    $xpath = new \DOMXPath($document);
    $elements_with_lang = $xpath->query('//*[@lang]');
    foreach ($elements_with_lang as $element) {
      if (strtolower($element->getAttribute('lang')) == 'x-none') {
        $element->removeAttribute('lang');
      }
      if (strtolower($element->getAttribute('lang')) == 'khm') {
        $element->setAttribute('lang', 'KM');
      }
    }
    $modified_text = HTML::serialize($document);

    return new FilterProcessResult($modified_text);
  }

}
