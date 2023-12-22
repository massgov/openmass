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
      console.log("hello");
      $(table).querySelectorAll('thead th').forEach(function (colHeader) {
          console.log("hello2");
        if (!$(colHeader).hasAttribute('scope')) {
          $(this).addAttr('scope', 'col');
        }
      });
    }

  });

})(jQuery);
