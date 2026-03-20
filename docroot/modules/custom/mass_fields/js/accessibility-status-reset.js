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
    attach: function (context) {
      const liveRegionId = 'accessibility-status-reset-announcement';

      // Inject an aria-live region once (for screen reader announcements).
      once('accessibility-status-live-region', 'body').forEach(function (body) {
        $(body).append(
          '<div id="' + liveRegionId + '" aria-live="polite" aria-atomic="true" ' +
          'class="visually-hidden"></div>'
        );
      });

      // Find all accessibility status field wrappers (works on standalone
      // media forms and on inline entity forms embedded in node forms).
      const $fieldWrappers = $('.js-accessibility-status-field', context);

      // Hide the _none (TBD) radio and its label in every wrapper.
      $fieldWrappers.find('input[value="_none"]').closest('.js-form-item').hide();

      // For each wrapper, track file changes and reset on upload/remove.
      once('accessibility-status-reset', '.js-accessibility-status-field', context).forEach(function (wrapper) {
        const $wrapper = $(wrapper);
        const $form = $wrapper.closest('form');
        // Find the file fids input within the same IEF/form group.
        const $entityForm = $wrapper.closest('.ief-form, .media-form, [data-drupal-selector]').first();
        let $fidsField = $entityForm.find('input[name*="[fids]"]');
        if (!$fidsField.length) {
          $fidsField = $form.find('input[data-drupal-selector*="fids"]');
        }

        if (!$fidsField.length) {
          return;
        }

        // Use object to track state across AJAX callbacks.
        const state = {lastFids: $fidsField.val() || ''};

        $(document).on('ajaxSuccess', function () {
          setTimeout(handleAjaxSuccess.bind(null, $wrapper, $entityForm, $form, state, liveRegionId), 100);
        });
      });
    }
  };

  /**
   * Handles AJAX success to detect file changes and reset the field.
   */
  function handleAjaxSuccess($wrapper, $entityForm, $form, state, liveRegionId) {
    // Re-find the fids field after AJAX (it may have been re-rendered).
    let $currentFids = $entityForm.find('input[name*="[fids]"]');
    if (!$currentFids.length) {
      $currentFids = $form.find('input[data-drupal-selector*="fids"]');
    }
    if (!$currentFids.length) {
      return;
    }

    const currentFids = $currentFids.val() || '';

    if (currentFids !== state.lastFids) {
      state.lastFids = currentFids;

      const $noneRadio = $wrapper.find('input[value="_none"]');
      const $otherRadios = $wrapper.find('input[type="radio"]').not($noneRadio);

      // Uncheck visible radios.
      $otherRadios.removeAttr('checked').prop('checked', false);

      // Check the hidden _none radio to reset the field.
      $noneRadio.attr('checked', 'checked').prop('checked', true);

      // Announce the reset to screen reader users.
      $('#' + liveRegionId).text(
        Drupal.t('File accessibility status has been reset. Please make a new selection.')
      );
    }

    // Re-hide _none after every AJAX update (Drupal may re-render it).
    $wrapper.find('input[value="_none"]').closest('.js-form-item').hide();
  }

})(jQuery, Drupal, once);
