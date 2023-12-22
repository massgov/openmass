/**
 * @file
 * Support responsive tables in rte
 * Test samples:  /info-details/qag-info-details-table-samples
 */

// Apply to all .ma__table.
(function ($) {
  'use strict';

  document.querySelectorAll('.ma__table').forEach(function (table) {
    // Set up assistive technology friendly tables.
    // 1. Add row scope for accessibility.
    $(table).find('tbody th').forEach(function (rowHeader) {
      $(rowHeader).attr('scope', 'row');
    });
  });

})(jQuery);
