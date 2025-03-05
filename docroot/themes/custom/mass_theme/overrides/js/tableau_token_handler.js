/**
 * @file
 * Tableau Token Handler (v3 – Connected Apps).
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.tableauTokenHandler = {
    attach: function (context, settings) {

      $('.ma_tableau_placeholder', context).each(function () {
        var $placeholder = $(this);
        var url = $placeholder.data('tableau-url');
        var tokenUrl = $placeholder.data('token-url');
        var id = $placeholder.attr('id');

        if (!tokenUrl) {
          console.error('Token URL missing.');
          return;
        }

        $.ajax({
          url: tokenUrl,
          type: 'GET',
          dataType: 'json',
          success: function (data) {
            if (data.token) {
              // Replace the placeholder with tableau-viz
              var tableauViz = $('<tableau-viz>', {
                'src': url,
                'id': id,
                'toolbar': 'bottom',
                'hide-tabs': '',
                'token': data.token
              });

              $placeholder.replaceWith(tableauViz);
            }
            else {
              console.error('Token not found in response.');
            }
          },
          error: function (xhr, status, error) {
            console.error('Error fetching Tableau token:', status, error);
          }
        });
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
