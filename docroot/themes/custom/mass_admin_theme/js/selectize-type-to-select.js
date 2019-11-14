/**
 * @file
 */
(function ($, Drupal) {

  'use strict';

  /**
   * Attach selectize library to select elements that have the class "use-selectize-autocomplete".
   */
  Drupal.behaviors.selectizeTypeToSelect = {
    attach: function (context, settings) {
      $('select.use-selectize-autocomplete').selectize({plugins: ['remove_button']});
    }
  };

})(jQuery, Drupal);
