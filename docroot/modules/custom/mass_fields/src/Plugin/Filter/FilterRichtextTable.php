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

    $tableWrapperTop = '<div class="ma__table--responsive js-ma-responsive-table"><div class="ma__table--responsive__wrapper" id="' . $tableId . '" role="group" tabindex="-1"><table class="ma__table"><caption id="tbl-' . $tableId . '" class="ma__table__caption"><span class="ma__table__caption__scroll-info"> Note: Table has hidden columns, scroll horizontally to see more.</span></caption>';
    $tableWrapperBottom = '</table></div></div>';
    $tableHeadingScope = '<th scope="col">';

    // Step 1: remove '<p>&nbsp;</p>' from the rich text input.
    $text = preg_replace('/<p>(\s|\xc2\xa0|&nbsp;)<\/p>/', '', $text);
    // Step 2: remove spaces in the cell with nested tables.
    $spaceInCellWithNestedTable = ['/<td>\s\s+<table>/', '/<\/table>\s\s+<\/td>/'];
    $cleanNestedTable = ['<td><table>', '</table></td>'];
    $text = preg_replace($spaceInCellWithNestedTable, $cleanNestedTable, $text);
    // Step 3: Add responsive table wrappers to non-nested tables.
    //         Add scope to th of all tables, nested and non-nested.
    $plainTableElements = ['/(?<!<td>)<table>/', '/<\/table>(?!<\/td>)/', '/<th>/'];
    $responsiveTableElements = [$tableWrapperTop, $tableWrapperBottom, $tableHeadingScope];
    $output = preg_replace($plainTableElements, $responsiveTableElements, $text);

    return new FilterProcessResult($output);

  }

}
