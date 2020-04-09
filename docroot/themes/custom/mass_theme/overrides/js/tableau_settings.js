/**
 * @file
 * Tableau Settings.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.tableauSettings = {
    attach: function (context, settings) {

      $(function () {
        const minDesktop = 800;
        const minTablet = 501;

        $('.ma_tableau_container', context).each(function () {
          let currentWidth = $(this).outerWidth();

          let deviceType = 'phone';
          if (currentWidth >= minDesktop) {
            deviceType = 'desktop';
          }
          else if (currentWidth >= minTablet) {
            deviceType = 'tablet';
          }

          let $tableauItem = $(this).children(".ma_tableau_item");
          let id = $tableauItem.attr("id");
          let url = $tableauItem.data("tableau-url");
          let options = { device: deviceType };

          new tableau.Viz(document.getElementById(id), url, options);
        });
      });

    }
  };

})(jQuery, Drupal, drupalSettings);
