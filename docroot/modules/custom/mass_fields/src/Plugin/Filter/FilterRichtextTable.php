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
    // Need to clean up '<p>&nbsp;</p>' and spaces before and after nested tables in their parent <td> for the last preg_replace.
    // regex for spaces cannot included to lookback and lookahead because it causes the warning in Drupal -
    // Warning: preg_replace(): Compilation failed: lookbehind assertion

    // Step 1: remove '<p>&nbsp;</p>' from the rich text input.
    $text = preg_replace('/<p>((\s|\xc2\xa0|&nbsp;)*?)<\/p>/', '', $text);
    // Step 2: remove spaces in the cell with nested tables.
    $spaceInCellWithNestedTable = ['/<td>\s\s+<table>/', '/<\/table>\s\s+<\/td>/'];
    $cleanNestedTable = ['<td><table>', '</table></td>'];
    $text = preg_replace($spaceInCellWithNestedTable, $cleanNestedTable, $text);

    $plainTableElements = ['/(?<!<td>)<table>/', '/<\/table>(?!<\/td>)/', '/<th>/'];
    $responsiveTableElements = [$tableWrapperTop, $tableWrapperBottom, $tableHeadingScope];

    // Step 3: Add responsive table wrappers to non-nested tables.
    //         Add scope to th of all tables, nested and non-nested.
    $output = preg_replace($plainTableElements, $responsiveTableElements, $text);

    return new FilterProcessResult($output);

  }

}
