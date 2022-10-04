<?php

namespace Drupal\mass_fields\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Add Filter to change dir attribute value to lowercase.
 *
 * @Filter(
 *   id = "filter_dir_attr",
 *   title = @Translation("Convert dir attribute value to lowercase in richtext."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 * )
 */
class FilterDirAttribute extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $output = preg_replace('/dir=[\"\']RTL[\"\']/i', 'dir="rtl"', $text);
    return new FilterProcessResult($output);

  }

}
