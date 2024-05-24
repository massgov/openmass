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
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 * )
 */
class FilterRichtextIndent extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    // Set default 2 since they are children of h2 until h3 shows up.
    $parentHeadingNum = 2;

    // Prep table for indentation + responsive.
    // Unique ID per table for accessibility to establish pairing between the buttons and the table container for responsible table.
    $tableId = uniqid();
    $tableWrapperTop = '<div class="ma__rich-text__indent ma__table--responsive js-ma-responsive-table" data-ma-heading-parent="H' . $parentHeadingNum . '">
    <nav class="ma__table__horizontal-nav"><button class="ma__table__horizontal-nav__left" aria-controls="' . $tableId . '"><span class="ma__visually-hidden">Scroll left</span></button><div class="clip-scrollbar"><div class="ma__scroll-indicator"><div class="ma__scroll-indicator--bar" aria-controls="' . $tableId . '" role="scrollbar" aria-orientation="horizontal"><div class="ma__scroll-indicator__button"></div></div></div></div><button class="ma__table__horizontal-nav__right" aria-controls="' . $tableId . '"><span class="ma__visually-hidden">Scroll right</span></button>
    </nav>
    <div class="ma__table--responsive__wrapper" id="' . $tableId . '">
    <table>';
    $tableWrapperBottom = '</table></div></div>';

    // Test for all container elements available in the editor.
    $plainElements = [
      '<h3>', '<h4>', '<h5>', '<h6>', '<p>', '<ul>', '<ol>', '<blockquote>',
      '<table>', '</table>', '<drupal-entity',
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
      $tableWrapperTop,
      $tableWrapperBottom,
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
