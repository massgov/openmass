/**
 * @file
 * Support responsive tables in rte
 * Test samples:  /info-details/qag-info-details-table-samples
 */

(function ($) {
  'use strict';

  document.querySelectorAll('.ma__table--responsive .ma__table').forEach(function (table) {
    // Copy captions authors entered into Mayflower template format.
    // table-responsive.twig L.12
    var userInputCaption = $(table).find('caption:not(.ma__table__caption)');
    // 2nd test prevents empty caption content container. Otherwise add extra space at the top of the table.
    if ($(table).find('caption:not(.ma__table__caption)') && $(table).find('caption:not(.ma__table__caption)').text().length > 0) {
      var captionText = $(userInputCaption).text();
      var captionSnippet = '<span class="ma__table__caption__content">' + captionText + '</span>';

      $(table).find('.ma__table__caption').prepend(captionSnippet);
      $(userInputCaption).remove();
      // Override JS in Mayflower when caption has content.
      $(table).find('.ma__table__caption').removeClass('hide');
    }

    // Check table cell count for .ma__table--wide.
    // table-responsive.twig L.4
    // Note: Some author entered tables have td instead of th.
    //       Also covers the case of no thead.
    if (
      $(table).find('thead th').length > 3 ||
      $(table).find('thead').find('td').length > 3 ||
      $(table).find('tbody tr:first-child *').length > 3) {
      $(table).addClass('ma__table--wide');
    }
  });

})(jQuery);
