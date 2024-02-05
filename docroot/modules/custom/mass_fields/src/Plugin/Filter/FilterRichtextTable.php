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

    $tableWrapperTop = '<div class="ma__table--responsive js-ma-responsive-table">
    <div class="ma__table--responsive__wrapper" id="' . $tableId . '" role="group" tabindex="-1">
    <table class="ma__table"><caption id="tbl-' . $tableId . '" class="ma__table__caption"><span class="ma__table__caption__scroll-info"> (Table in a horizontal scrolling container)</span></caption>';
    $tableWrapperBottom = '</table></div></div>';
    $tableHeadingScope = '<th scope="col">';

    $nonNestedTableOpen = '/(?<!<td>)(?<!<td><p>&nbsp;<\/p>)<table>/';
    $nonNestedTableClose = '/<\/table>(?!<\/td>)/';
    $text = preg_replace($nonNestedTableOpen, $tableWrapperTop, $text);
    $text = preg_replace($nonNestedTableClose, $tableWrapperBottom, $text);
    // // When RTE adds <p>&nbsp;</p> to a table cell with a nested table.
    // $text = preg_replace('/(?<!<td><p>&nbsp;<\/p>)<table>/', $tableWrapperTop, $text);
    // $text = preg_replace('/<\/table>(?!<p>&nbsp;<\/p><\/td>)/', $tableWrapperBottom, $text);
    // $nestedTablePatterns = ['/(?<!<td>)<table>/', '/(?<!<td><p>&nbsp;<\/p>)<table>/', '/<\/table>(?!<\/td>)/', '/<\/table>(?!<p>&nbsp;<\/p><\/td>)/'];
    // $nestedTableOpenPatterns = ['/(?<!<td><p>\&nbsp;<\/p>)<table>/', '/(?<!<td>)<table>/'];
    // $nestedTableClosePatterns = ['/<\/table>(?!<p>\&nbsp;<\/p><\/td>)/', '/<\/table>(?!<\/td>)/'];
    // $text = preg_replace($nestedTableOpenPatterns, $tableWrapperTop, $text);
    // $text = preg_replace($nestedTableClosePatterns, $tableWrapperBottom, $text);


    // $nonNestedTables = [$tableWrapperTop, $tableWrapperBottom];
    // $cleanTables = preg_replace($nestedTablePatterns, $nonNestedTables, $text);

    // $plainTableElements = [$nonNestedTableOpen, $nonNestedTableClose, '<th>'];
    // $responsiveTableElements = [$tableWrapperTop, $tableWrapperBottom, $tableHeadingScope];
    // $output = str_replace($plainTableElements, $responsiveTableElements, $tableCleaning2);
    $output = str_replace('<th>', $tableHeadingScope, $text);


    return new FilterProcessResult($output);

  }

}
