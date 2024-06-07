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
        });
      });
    }


    // Responsive tables
    if ($(table).closest('.js-responsive-table')) {
      // Copy captions authors entered into Mayflower template format.
      // table-responsive.twig L.12
      $(table).find('caption:not(.ma__table__caption)').addClass('ma__table__caption');

      // Check table cell count for .ma__table--wide.
      // table-responsive.twig L.4
      // Note: Some author entered tables have td instead of th in thead.
      //       Also covers the case of no thead.
      if (
        $(table).find('thead th').length > 3 ||
        $(table).find('thead').find('td').length > 3 ||
        $(table).find('tbody tr:first-child *').length > 3
      ) {
        $(table).addClass('ma__table--wide');
      }
    }

  });

})(jQuery);
