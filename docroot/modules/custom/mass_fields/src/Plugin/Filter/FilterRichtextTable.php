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

    // Exclude nesting tables from the string replacement.
    $plainTableElements = ['/(?<!\<td>\s\s+\<p>&nbsp;\<\/p>\s\s+)<table>/', '/<\/table>(?!<\/td>)/', '<th>'];
    // $plainTableElements = ['<table>', </table>, '<th>'];
    $responsiveTableElements = [$tableWrapperTop, $tableWrapperBottom, $tableHeadingScope];

    $output = preg_replace($plainTableElements, $responsiveTableElements, $text);
    // $output = str_replace($plainTableElements, $responsiveTableElements, $text);

    return new FilterProcessResult($output);

  }

}
