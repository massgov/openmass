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
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
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

    $scrollIcon = '<svg aria-hidden="true" focusable="false" width="18" height="15"><path d="m17.917 7.522-7.884 7.39-2.3-2.156 5.581-5.234-5.582-5.234 2.3-2.157 7.885 7.39Z"></path><path d="m10.06 7.522-7.884 7.39-2.3-2.156 5.581-5.234-5.582-5.234 2.3-2.157 7.885 7.39Z"></path></svg>';
    $tableWrapperTop = '<div class="ma__table--responsive js-ma-responsive-table"><div class="ma__table--responsive__wrapper" id="' . $tableId . '" role="group" tabindex="-1"><table class="ma__table">';
    $tableWrapperBottom = '</table></div></div>';
    $tableHeadingScope = '<th scope="col">';

    // Step 1:
    // Remove '<p>&nbsp;</p>' from the rich text input.
    $text = preg_replace('/<p>(\s|\xc2\xa0|&nbsp;)<\/p>/', '', $text);
    // Step 2:
    // Remove spaces in the cell with nested tables.
    $spaceInCellWithNestedTable = ['/<td>\s\s+<table>/', '/<\/table>\s\s+<\/td>/'];
    $cleanNestedTable = ['<td><table>', '</table></td>'];
    $text = preg_replace($spaceInCellWithNestedTable, $cleanNestedTable, $text);
    // Step 3:
    // Add responsive table wrappers to non-nested tables.
    // Add scope to th of all tables, nested and non-nested.
    $plainTableElements = ['/(?<!<td>)<table>/', '/<\/table>(?!<\/td>)/', '/<th>/'];
    $responsiveTableElements = [$tableWrapperTop, $tableWrapperBottom, $tableHeadingScope];
    $output = preg_replace($plainTableElements, $responsiveTableElements, $text);

    return new FilterProcessResult($output);

  }

}
