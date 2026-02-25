(function (Drupal, $, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.massUnpublishWarn = {
    attach: function (context) {
      var settings = (drupalSettings && drupalSettings.massEntityUsage) || {};
      var count = parseInt(settings.linkingPagesCount || 0, 10);
      var unpublishStates = settings.unpublishStates || ['archived', 'unpublished', 'trash'];
      var title = settings.modalTitle || 'Heads up';
      var msg1 = settings.modalMessageSingular;
      var msgN = settings.modalMessagePlural;


      // If nothing links here, there is nothing to warn about.
      if (!count || count <= 0) {
        return;
      }

      once('mass-unpublish-warn', 'form.node-form', context).forEach(function (formEl) {
        var $form = $(formEl);

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

        function openConfirm() {
          var text = (count === 1) ? msg1 : msgN.replace('@count', count);
          var $wrapper = $('<div class="mass-linking-unpublish-modal"><p>' + text + '</p></div>');

          var dialog = Drupal.dialog($wrapper.get(0), {
            title: title,
            width: 600,
            buttons: [
              {
                text: Drupal.t('Continue and move to @state', {'@state': getTargetStateLabel()}),
                classes: 'button button--primary',
                click: function () {
                  // 1) mark confirmed
                  $form.find('input[name="mass_linking_unpublish_confirmed"]').val('1');

                  // 2) close modal
                  dialog.close();

                  // 3) unbind our submit handler to avoid re-intercepting
                  $form.off('submit.mass-unpublish-warn');

                  // 4) prefer clicking the actual submit button (some workflows attach handlers there)
                  var $submit = $form.find('.form-actions [type="submit"]:not([disabled]):first');

                  setTimeout(function () {
                    if ($submit.length) {
                      $submit[0].click();
                    }
                    else {
                      $form.get(0).submit();
                    }
                  }, 0);
                }
              },
              {
                text: Drupal.t('Cancel'),
                classes: 'button',
                click: function () { dialog.close(); }
              }
            ],
            closeOnEscape: true
          });
          dialog.showModal();
        }

        $form.on('submit.mass-unpublish-warn', function (e) {
          // If already confirmed, proceed.
          if ($form.find('input[name="mass_linking_unpublish_confirmed"]').val() === '1') {
            return true;
          }

          var state = getTargetState();
          var isUnpublishing = state && unpublishStates.indexOf(state) !== -1;

          if (isUnpublishing) {
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
