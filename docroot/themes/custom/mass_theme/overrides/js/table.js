/**
 * @file
 * Support responsive tables in rte
 * Test samples:  /info-details/qag-info-details-table-samples
 */

// Apply to all .ma__table.
(function ($) {
  'use strict';

  document.querySelectorAll('.ma__table').forEach(function (table) {
    var hasRowHeaders = false;

    // Set up assistive technology friendly tables.
    // Add row scope for accessibility.
    $(table).find('tbody th').each(function () {
      $(this).attr('scope', 'row');
      hasRowHeaders = true;
    });

    // Mobile format for table
    if ($(table).has('thead th').length > 0) {
      // 1. Set up mobile headers
      var headerLabels = [];
      $(table).find('thead th').each(function (headerIndex) {
        // var headerLabel = $(this).text();
        headerLabels.push($(this).text());
      });

      // Loop each row.
      $(table).find('tbody tr').each(function () {
        var targetCells = $(this).find('td');

        $(targetCells).each(function (cellIndex) {
          var headerIndex = cellIndex;

          if (hasRowHeaders) {
            headerIndex = cellIndex + 1;
          }

          var headerLabel = headerLabels[headerIndex];
          $(this).attr('data-label', headerLabel);
        });
      });
    }
    else {
      // No row headers
      // Remove left space for headers with css.
      if ($(window).width() < 781) {
        $(table).addClass('no-headers');
      }
    }
  });

})(jQuery);
