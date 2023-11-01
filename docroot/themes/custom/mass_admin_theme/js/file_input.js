/**
 * @file
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.fileInputButton = {
    attach: function (context, settings) {
      var $surrogateButtons = $('button.mass-input-file', context);
      $surrogateButtons.once()
        .after('<span class="no-file-chosen">No file chosen</span>')
        .on('click', function (event) {
          var $realButton = $(this).next().next();
          $realButton.click();
        });
    }
  };

})(jQuery, Drupal);
