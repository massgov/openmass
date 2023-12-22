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
    // Add row scope for accessibility.
    $(table).find('tbody th').each(function () {
      $(this).attr('scope', 'row');
    });

    // Mobile format for table
    if ($(table).has('thead th').length > 0) {
      // 1. Set up mobile headers
      $(table).find('thead th').each(function (hIndex) {
        var headerLabel = $(this).text();

        // Loop each row.
        $(table).find('tbody tr').each(function () {
          var targetCells = $(this).find('td');

          if ($(this).has("th").length > 0) {
            hIndex--;
          }

          // Find matching column cell in each row to apply data-label.
          $(targetCells).each(function (cellIndex) {
            if (cellIndex == hIndex && (headerLabel.length > 0)) {
              $(this).attr("data-label", headerLabel);
            }
          });
        });
      });
    }
    else {
      // No row headers
      if ($(window).width() < 621) {
        $(table).find('tbody tr *').each(function () {
          $(this).css('padding-left', '0');
          $(this).css('width', '100%');
        });
      }
    }
  });

})(jQuery);
