/**
 * @file
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.fileInputButton = {
    attach: function (context, settings) {
      var $surrogateButtons = $(once('surrogate-buttons', 'button.mass-input-file', context));
      $surrogateButtons.each(function () {
        $(this).after('<span class="no-file-chosen">No file chosen</span>')
          .on('click', function (event) {
            var $realButton = $(this).next().next();
            $realButton.click();
          });
      });
    }
  };

})(jQuery, Drupal);
