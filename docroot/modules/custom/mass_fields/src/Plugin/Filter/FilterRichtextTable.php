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
    $tableId = uniqid();

    $tableWrapperTop = '<div class="ma__table--responsive js-ma-responsive-table">
    <nav class="ma__table__horizontal-nav"><button class="ma__table__horizontal-nav__left" aria-controls="' . $tableId . '"><span class="ma__visually-hidden">Scroll left</span></button><div class="clip-scrollbar"><div class="ma__scroll-indicator"><div class="ma__scroll-indicator--bar" aria-controls="' . $tableId . '" role="scrollbar" aria-orientation="horizontal"><div class="ma__scroll-indicator__button"></div></div></div></div><button class="ma__table__horizontal-nav__right" aria-controls="' . $tableId . '"><span class="ma__visually-hidden">Scroll right</span></button>
    </nav>
    <div class="ma__table--responsive__wrapper" id="' . $tableId . '">
    <table>';
    $tableWrapperBottom = '</table></div></div>';

    $plainTableElements = ['<table>', '</table>'];
    $responsiveTableElements = [$tableWrapperTop, $tableWrapperBottom];
    $output = str_replace($plainTableElements, $responsiveTableElements, $text);

    return new FilterProcessResult($output);

  }

}
