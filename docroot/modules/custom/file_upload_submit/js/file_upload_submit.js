/**
 * @file
 * Attach behaviors for file upload form submit handling.
 */
(function ($) {
  'use strict';

  /**
   * Setup and attach the file upload submit functionality.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.fileUploadSubmit = {
    attach: function (context) {

      var $form = $('form.node-form', context);
      var $target = $form.find('.form-actions input[type=submit]', context);
      var field = $target.get(0);

      // Prevent form submit if AJAX action is in progress.
      $target.click(function () {
        if ($.active > 0) {
          if (field.reportValidity) {
            // Report validity if we're able to do so.
            field.setCustomValidity('File upload in progress');
            field.reportValidity();
          }
          return false;
        }
        else {
          if (field.reportValidity) {
            field.setCustomValidity('');
          }
        }
      });

    }
  };
})(jQuery);
