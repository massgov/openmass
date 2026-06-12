/**
 * Reloads the org mapping matrix when the "items per page" select changes.
 *
 * @param {Drupal} Drupal Drupal global object providing behaviors registry.
 * @param {Function} once Core/once helper for one-time element processing.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.oogItemsPerPage = {
    attach: function (context) {
      const selector = '[data-oog-items-per-page]';
      once('oog-items-per-page', selector, context).forEach(function (select) {
        select.addEventListener('change', function () {
          const url = new URL(window.location.href);
          url.searchParams.set('items', select.value);
          // Reset to the first page when the page size changes.
          url.searchParams.delete('page');
          window.location.href = url.toString();
        });
      });
    }
  };
})(Drupal, once);
