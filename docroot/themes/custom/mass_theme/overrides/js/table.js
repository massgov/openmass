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

      $(window).on('resize', function () {
        if ($(window).width() < 781) {
          $(table).addClass('no-headers');
        }
      });
    }


    // Responsive tables
    if ($(table).closest('.js-responsive-table')) {
      // Copy captions authors entered into Mayflower template format.
      // table-responsive.twig L.12
      var userInputCaption = $(table).find('caption:not(.ma__table__caption)');
      // 2nd test prevents empty caption content container. Otherwise add extra space at the top of the table.
      if (
        $(table).find('caption:not(.ma__table__caption)') &&
        $(table).find('caption:not(.ma__table__caption)').text().length > 0
      ) {
        var captionText = $(userInputCaption).text();
        var captionSnippet =
          '<span class="ma__table__caption__content">' +
          captionText +
          '</span>';

        $(table).find('.ma__table__caption').prepend(captionSnippet);
        $(userInputCaption).remove();
        // Override JS in Mayflower when caption has content.
        $(table).find('.ma__table__caption').removeClass('hide');
      }

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
