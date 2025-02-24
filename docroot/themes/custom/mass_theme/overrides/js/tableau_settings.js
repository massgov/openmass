/**
 * @file
 * Tableau Settings (v2 – iframe-based).
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.tableauSettings = {
    attach: function (context, settings) {
      $(function () {
        var minDesktop = 800;
        var minTablet = 501;

        $('.ma_tableau_container', context).each(function () {
          var $tableauItem = $(this).children('.ma_tableau_item');

          // Skip this if it's a v3 (Connected Apps) embed.
          if ($tableauItem.data('token-url')) {
            return;
          }

          var currentWidth = $(this).outerWidth();

          var deviceType = 'phone';
          if (currentWidth >= minDesktop) {
            deviceType = 'desktop';
          }
          else if (currentWidth >= minTablet) {
            deviceType = 'tablet';
          }

          var id = $tableauItem.attr('id');
          var url = $tableauItem.data('tableau-url');
          var options = { device: deviceType };

          /* eslint-disable */
          new tableau.Viz(document.getElementById(id), url, options);
          /* eslint-enable */
        });
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
