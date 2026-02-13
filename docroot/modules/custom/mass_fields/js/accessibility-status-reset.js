/**
 * @file
 * Reset the accessibility status field when a document is removed.
 */

(function ($, Drupal, once) {
  'use strict';

  /**
   * Resets the accessibility status radio buttons when file is removed.
   */
  Drupal.behaviors.accessibilityStatusReset = {
    attach: function (context, settings) {
      // Listen for AJAX commands to detect when a file is removed.
      $(document).on('ajaxSuccess', function (event, xhr, ajaxSettings) {
        // Small delay to let DOM update after AJAX.
        setTimeout(function () {
          // Check if the fids field is empty (file was removed).
          const $fidsField = $('input[data-drupal-selector="edit-field-upload-file-0-fids"]');

          if ($fidsField.length && !$fidsField.val()) {
            // File was removed, reset accessibility status to _none.
            const $form = $fidsField.closest('form');
            const $accessibilityRadios = $form.find('input[name="field_accessibility_self_rpt"]');

            // Uncheck all radios (reset to no selection).
            $accessibilityRadios.prop('checked', false).trigger('change');
          }
        }, 100);
      });
    }
  };

})(jQuery, Drupal, once);
