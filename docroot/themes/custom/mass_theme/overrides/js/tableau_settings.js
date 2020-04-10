/**
 * @file
 * Tableau Settings.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.tableauSettings = {
    attach: function (context, settings) {

      $(function () {
        var minDesktop = 800;
        var minTablet = 501;

        $('.ma_tableau_container', context).each(function () {
          var currentWidth = $(this).outerWidth();

          var deviceType = 'phone';
          if (currentWidth >= minDesktop) {
            deviceType = 'desktop';
          }
          else if (currentWidth >= minTablet) {
            deviceType = 'tablet';
          }

          var $tableauItem = $(this).children('.ma_tableau_item');
          var id = $tableauItem.attr('id');
          var url = $tableauItem.data('tableau-url');
          var options = {device: deviceType};

          /* eslint-disable */
          new tableau.Viz(document.getElementById(id), url, options);
          /* eslint-enable */

        });
      });

    }
  };

})(jQuery, Drupal, drupalSettings);
