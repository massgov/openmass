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
   * resetting the field to the empty state. An aria-live region announces
   * the reset to screen reader users. The require_on_publish constraint
   * blocks publishing until the author picks a real option.
   */
  Drupal.behaviors.accessibilityStatusReset = {
    attach: function (context, settings) {
      const fidsSelector = 'input[data-drupal-selector="edit-field-upload-file-0-fids"]';
      const noneSelector = 'input[name="field_accessibility_self_rpt"][value="_none"], input[name="media[document][field_accessibility_self_rpt]"][value="_none"]';
      const liveRegionId = 'accessibility-status-reset-announcement';

      // Inject an aria-live region once (for screen reader announcements).
      once('accessibility-status-live-region', 'body').forEach(function (body) {
        $(body).append(
          '<div id="' + liveRegionId + '" aria-live="polite" aria-atomic="true" ' +
          'class="visually-hidden"></div>'
        );
      });

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

            const $form = $fidsField.closest('form');
            const $noneRadio = $form.find(noneSelector);
            const $otherRadios = $form.find('input[name="field_accessibility_self_rpt"]').not(noneSelector);

            // Uncheck visible radios (attribute + property).
            $otherRadios.removeAttr('checked').prop('checked', false);

            // Check the hidden _none radio to reset the field to an empty state.
            $noneRadio.attr('checked', 'checked').prop('checked', true);

            // Announce the reset to screen reader users.
            $('#' + liveRegionId).text(
              Drupal.t('File accessibility status has been reset. Please make a new selection.')
            );
          }

          // Re-hide _none after every AJAX update (Drupal may re-render it).
          $(noneSelector).closest('.js-form-item').hide();
        }, 100);
      });
    }
  };

})(jQuery, Drupal, once);
