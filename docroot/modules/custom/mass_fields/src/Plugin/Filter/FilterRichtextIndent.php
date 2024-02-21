<?php

namespace Drupal\mass_fields\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Add css hook for indentation.
 *
 * @Filter(
 *   id = "filter_richtext_indent",
 *   title = @Translation("Add css hooks to indent each child heading and its nested contents"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 * )
 */
class FilterRichtextIndent extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    // Set default 2 since they are children of h2 until h3 shows up.
    $parentHeadingNum = 2;

    $plainElements = [
      '<h3>', '<h4>', '<h5>', '<h6>', '<p>', '<ul>', '<ol>', '<blockquote>',
      '<drupal-entity',
    ];

    $defaultIndentedElements = [
      '<h3 class="ma__rich-text__indent">',
      '<h4 class="ma__rich-text__indent">',
      '<h5 class="ma__rich-text__indent">',
      '<h6 class="ma__rich-text__indent">',
      '<p class="ma__rich-text__indent" data-ma-heading-parent="H' . $parentHeadingNum . '">',
      '<ul class="ma__rich-text__indent" data-ma-heading-parent="H' . $parentHeadingNum . '">',
      '<ol class="ma__rich-text__indent" data-ma-heading-parent="H' . $parentHeadingNum . '">',
      '<blockquote class="ma__rich-text__indent" data-ma-heading-parent="H' . $parentHeadingNum . '">',
      '<table>',
      '</table>',
      '<drupal-entity class="ma__rich-text__indent" data-ma-heading-parent="H' . $parentHeadingNum . '" ',
    ];

    // Initial replacement.
    $initialConversion = str_replace($plainElements, $defaultIndentedElements, $text);

    // Add delimiters to the string.
    $initialConversion = str_replace('<h', '*<h', $initialConversion);
    // Convert the string to an array.
    $headingLevelContent = explode('*', $initialConversion);

    // Update $parentHeadingNum accordingly.
    foreach ($headingLevelContent as &$headingUnit) {
      // It does nothing if an array element doesn't have a heading.
      if (strpos($headingUnit, '<h') !== FALSE) {
        for ($i = 3; $i <= 6; $i++) {
          $headingLevel = '<h' . $i;
          if (strpos($headingUnit, $headingLevel) !== FALSE) {
            $updatedValue = 'H' . $i;
            $headingUnit = str_replace('H2', $updatedValue, $headingUnit);
          }
        }
      }
    }

    // Merge updated array elements to one string.
    $output = implode($headingLevelContent);

    return new FilterProcessResult($output);

  }

}
