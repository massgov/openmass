(function (Drupal, $, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.massUnpublishWarn = {
    attach: function (context) {
      var settings = (drupalSettings && drupalSettings.massEntityUsage) || {};
      var count = parseInt(settings.linkingPagesCount || 0, 10);
      var unpublishStates = settings.unpublishStates || ['archived', 'unpublished', 'trash'];
      var title = settings.modalTitle || 'Heads up';
      var modalMessage = settings.modalMessage;


      // If nothing links here, there is nothing to warn about.
      if (!count || count <= 0) {
        return;
      }

      once('mass-unpublish-warn', 'form.node-form, form.media-form', context).forEach(function (formEl) {
        var $form = $(formEl);
        var pendingSubmitter = null;

        function getTargetState() {
          var nested = $form.find('[name="moderation_state[0][state]"]').val();
          if (nested) {return nested;}

          var alt = $form.find('[name="moderation_state__target_state"]').val();
          if (alt) {return alt;}

          var anySelect = $form.find('select[name*="moderation_state"]').val();
          if (anySelect) {return anySelect;}

          var anyHidden = $form.find('input[type="hidden"][name*="moderation_state"]').val();
          if (anyHidden) {return anyHidden;}

          var dataSel = $form.find('[data-drupal-selector*="moderation-state"]').val();
          return dataSel || null;
        }

        function getTargetStateLabel() {
          var $select = $form.find('select[name*="moderation_state"] option:selected');
          if ($select.length) {
            return $select.text().trim();
          }

          // Fallback: try any visible label near moderation widget.
          var text = $form.find('[data-drupal-selector*="moderation-state"]').text().trim();
          return text || Drupal.t('Unpublish');
        }

        /**
         * Returns the primary Save button in the form footer.
         *
         * Node forms contain many nested .form-actions regions (paragraphs,
         * IEF, etc.). The main Save button lives in the page footer.
         */
        function getMainSubmitButton() {
          var footerSelectors = [
            '.layout-region--footer .form-actions',
            '.layout-region-node-footer .form-actions',
            '.layout-region-doc-footer .form-actions',
            '#edit-actions',
          ];
          var $footerSubmit;
          var i;

          for (i = 0; i < footerSelectors.length; i++) {
            $footerSubmit = $form.find(footerSelectors[i] + ' [type="submit"]:not([disabled])');
            if ($footerSubmit.length) {
              return $footerSubmit.last();
            }
          }

          return $();
        }

        function resubmitForm() {
          var form = formEl;
          var submitter = pendingSubmitter;

          $form.find('input[name="mass_linking_unpublish_confirmed"]').val('1');
          $form.off('submit.mass-unpublish-warn click.mass-unpublish-warn-submit');

          if (!submitter || !form.contains(submitter)) {
            var $mainSubmit = getMainSubmitButton();
            submitter = $mainSubmit.length ? $mainSubmit.get(0) : null;
          }

          if (submitter && typeof form.requestSubmit === 'function') {
            form.requestSubmit(submitter);
            return;
          }

          if (submitter) {
            submitter.click();
            return;
          }

          form.submit();
        }

        function openConfirm() {
          var text = modalMessage;
          var $wrapper = $('<div class="mass-linking-unpublish-modal"><p>' + text + '</p></div>');

          var dialog = Drupal.dialog($wrapper.get(0), {
            title: title,
            width: 600,
            buttons: [
              {
                text: Drupal.t('Continue and move to @state', {'@state': getTargetStateLabel()}),
                classes: 'button button--primary',
                click: function () {
                  dialog.close();
                  // Allow the dialog to close before resubmitting.
                  setTimeout(resubmitForm, 0);
                }
              },
              {
                text: Drupal.t('Cancel'),
                classes: 'button',
                click: function () {
                  pendingSubmitter = null;
                  dialog.close();
                }
              }
            ],
            closeOnEscape: true
          });
          dialog.showModal();
        }

        // Track which submit button initiated the save (node forms have many).
        $form.on('click.mass-unpublish-warn-submit', '[type="submit"]', function () {
          pendingSubmitter = this;
        });

        $form.on('submit.mass-unpublish-warn', function (e) {
          // If already confirmed, proceed.
          if ($form.find('input[name="mass_linking_unpublish_confirmed"]').val() === '1') {
            return true;
          }

          var state = getTargetState();
          var isUnpublishing = state && unpublishStates.indexOf(state) !== -1;

          if (isUnpublishing) {
            if (e.originalEvent && e.originalEvent.submitter) {
              pendingSubmitter = e.originalEvent.submitter;
            }
            e.preventDefault();
            openConfirm();
            return false;
          }

          return true;
        });
      });
    }
  };

})(Drupal, jQuery, drupalSettings, once);
