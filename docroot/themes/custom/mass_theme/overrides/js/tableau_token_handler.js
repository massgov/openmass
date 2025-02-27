/**
 * @file
 * Tableau Token Handler (v3 – Connected Apps).
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.tableauTokenHandler = {
    attach: function (context, settings) {

      $('.ma_tableau_container', context).each(function () {
        var $tableauItem = $(this).children('.ma_tableau_item tableau-viz');
        var tokenUrl = $tableauItem.data('token-url');

        if (!tokenUrl) {
          return;
        }

        $.ajax({
          url: tokenUrl,
          type: 'GET',
          dataType: 'json',
          success: function (data) {
            if (data.token) {
              // Modify HTML to include the token attribute
              $tableauItem.attr('token', data.token);
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
