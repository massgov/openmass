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
    // Also, any spaces, not `&nbsp;`, added during authoring activities remain in the table cell.
    // Search and replace feature doesn't work with spaces as it doesn't see "exact match" to replace.
    // Need to clean up spaces before and after nested tables in their parent <td> to avoid
    // Warning: preg_replace(): Compilation failed: lookbehind assertion
    // preg_replace with these regex work as tested in the online regex validator for PHP,
    // not working in Drupal. No replacement happens. Empty paragraphs and spaces remain.
    $spaceInCellWithNestedTable = ['/<td>\s\s+\<p>&nbsp;\<\/p>\s\s+<table>/', '/<\/table><p>&nbsp;<\/p>\s\s+<\/td>/'];
    $noSpace = ['/<td><table>/', '/<\/table><\/td>/'];
    $text = preg_replace($spaceInCellWithNestedTable, $noSpace, $text);

    // Exclude nesting tables (<table> in <td>) from the string replacement.
    // preg_replace with these regex work as tested in the online regex validator for PHP,
    $plainTableElements = ['/(?<!\<td>)<table>/', '/<\/table>(?!\<\/td>)/', '/<th>/'];
    $responsiveTableElements = [$tableWrapperTop, $tableWrapperBottom, $tableHeadingScope];

    // not working in Drupal. Replacement happens to both nested and non-nested tables.
    // partially because of the failure of  the prior preg_repalcement to remove spaces and empty paragraphs for nested table containers.
    $output = preg_replace($plainTableElements, $responsiveTableElements, $text);

    return new FilterProcessResult($output);

  }

}
