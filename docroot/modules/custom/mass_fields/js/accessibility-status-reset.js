/**
 * @file
 * Reset the accessibility status field when a document file is removed or replaced.
 */

(function ($, Drupal, once) {
  'use strict';

  /**
   * Hides the TBD (_none) option and resets to it on file change.
   *
   * The _none (TBD) radio is kept in the DOM but hidden. When a file is
   * uploaded or removed, JS selects the hidden _none radio — effectively
   * resetting the field to "no selection". The require_on_publish constraint
   * will then block publishing until the author picks a real option.
   */
  Drupal.behaviors.accessibilityStatusReset = {
    attach: function (context, settings) {
      const fidsSelector = 'input[data-drupal-selector="edit-field-upload-file-0-fids"]';
      const noneSelector = 'input[data-drupal-selector="edit-field-accessibility-self-rpt-none"]';

      // Hide the _none (TBD) radio and its wrapper on every attach
      // (including after AJAX re-renders).
      $(noneSelector, context).closest('.js-form-item').hide();

      // Track fids to detect file changes.
      let lastFids = $(fidsSelector).val() || '';

      $(document).on('ajaxSuccess', function (event, xhr, ajaxSettings) {
        setTimeout(function () {
          const $fidsField = $(fidsSelector);
          if (!$fidsField.length) {
            return;
          }

          const currentFids = $fidsField.val() || '';

          if (currentFids !== lastFids) {
            lastFids = currentFids;

            // Reset to _none: set both attribute and property so the hidden
            // radio is checked, and the visible ones are unchecked.
            const $form = $fidsField.closest('form');
            const $noneRadio = $form.find(noneSelector);
            const $otherRadios = $form.find('input[name="field_accessibility_self_rpt"]').not(noneSelector);

            // Uncheck visible radios (attribute + property).
            $otherRadios.removeAttr('checked').prop('checked', false);

            // Check the hidden _none radio.
            $noneRadio.attr('checked', 'checked').prop('checked', true);
          }

          // Re-hide _none after every AJAX update (Drupal may re-render it).
          $(noneSelector).closest('.js-form-item').hide();
        }, 100);
      });
    }
  };

})(jQuery, Drupal, once);
