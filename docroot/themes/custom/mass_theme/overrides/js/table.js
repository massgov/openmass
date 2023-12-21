/**
 * @file
 * Support responsive tables in rte
 * Test samples:  /info-details/qag-info-details-table-samples
 */

(function ($) {
  'use strict';

  document.querySelectorAll('.ma__table').forEach(function (table) {
    // Set up assistive technology friendly tables.
    if ($(table).find('thead')) {
      $(table).find('thead tr th').forEach(function (colHeader) {
        $(colHeader).addAttr('scope', 'col');
      });
    }

  });

})(jQuery);
