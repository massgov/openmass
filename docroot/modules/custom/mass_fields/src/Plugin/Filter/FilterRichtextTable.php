<?php

namespace Drupal\mass_fields\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Add hooks for responsiveTable.js.
 *
 * @Filter(
 *   id = "filter_richtext",
 *   title = @Translation("Convert &lt;table&gt;s in richtext responsive."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 * )
 */
class FilterRichtextTable extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    // Unique ID per table for accessibility to establish pairing between the buttons and the table container for responsible table.
    // Exclude nested tables.
    $tableId = uniqid();

    $tableWrapperTop = '<div class="ma__table--responsive js-ma-responsive-table"><div class="ma__table--responsive__wrapper" id="' . $tableId . '" role="group" tabindex="-1"><table class="ma__table"><caption id="tbl-' . $tableId . '" class="ma__table__caption"><span class="ma__table__caption__scroll-info"> (Table in a horizontal scrolling container)</span></caption>';
    $tableWrapperBottom = '</table></div></div>';
    $tableHeadingScope = '<th scope="col">';

    // In RTE, '<p>&nbsp;</p>' is added as a cell is clicked. When a table is added to the cell the empty paragraph remains.
    // Need to clean up spaces before and after nested tables in their parent <td> to avoid
    // Warning: preg_replace(): Compilation failed: lookbehind assertion
    $text = preg_replace('/<td>\s\s+\<p>&nbsp;\<\/p>\s\s+<table>/', '/<td><table>/', $text);
    $text = preg_replace('/<\/table>\s\s+\<p>&nbsp;<\/p>\s\s+<\/td>/', '/<\/table><\/td>/', $text);

    // Exclude nesting tables (<table> in <td>) from the string replacement.
    // the lookahead and lookback are not working in Drupal:
    // '/(?<!\<td>\<p>&nbsp;\<\/p>)<table>/` and '/<\/table>(?!\<\/td>)/'
    // As regex, they work as tested in the online regex validator for PHP.
    $plainTableElements = ['/(?<!\<td>\<p>&nbsp;\<\/p>)<table>/', '/<\/table>(?!\<\/td>)/', '/<th>/'];
    $responsiveTableElements = [$tableWrapperTop, $tableWrapperBottom, $tableHeadingScope];

    $output = preg_replace($plainTableElements, $responsiveTableElements, $text);
    // $output = str_replace($plainTableElements, $responsiveTableElements, $text);

    return new FilterProcessResult($output);

  }

}
