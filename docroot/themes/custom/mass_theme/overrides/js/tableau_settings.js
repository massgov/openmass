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
          var $tableauContainer = $(this);
          var $tableauItem = $tableauContainer.children('.ma_tableau_item');

          // Skip execution if the container has the v3 class (.tableau-connected-apps)
          if ($tableauContainer.hasClass('tableau-connected-apps')) {
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
          $tableauContainer.attr('data-tableau-state', 'loading');
          const needsRefresh = $tableauContainer.closest('.tableau-needs-refresh').length > 0;
          var options = {
            device: deviceType,
            onFirstInteractive: function () {
              $tableauContainer.attr('data-tableau-state', 'ready');
              if (needsRefresh) {
                window.dispatchEvent(new Event('resize'));
              }
            }
          };

          /* eslint-disable */
          new tableau.Viz(document.getElementById(id), url, options);
          /* eslint-enable */
        });
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
