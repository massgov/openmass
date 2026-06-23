/* checkov:skip=CKV3_SAST_74: This file does not use postMessage; event listeners are scoped to internal buttons, sanitized, and required for Drupal AJAX confirmation UX. */
(function (Drupal, once, $, drupalSettings) {
  'use strict';
  Drupal.behaviors.mfrDeleteConfirm = {
    attach(context) {
      once('mfrDeleteConfirm', '.mfr-delete', context).forEach((btn) => {
        const intercept = (e) => {
          e.preventDefault();
          e.stopImmediatePropagation();

          const message = btn.getAttribute('data-confirm') ||
            Drupal.t('Delete this friendly URL? This may break links if people are using it.');

          const container = document.createElement('div');
          container.innerHTML = `<p>${Drupal.checkPlain(message)}</p>`;

          const dialog = Drupal.dialog(container, {
            title: Drupal.t('Confirm delete'),
            modal: true,
            buttons: [
              {
                text: Drupal.t('Delete'),
                class: 'button button--danger',
                click: function () {
                  dialog.close();
                  // Trigger the custom event that Drupal #ajax listens to.
                  setTimeout(() => { $(btn).trigger('mfr-confirmed'); }, 0);
                }
              },
              {text: Drupal.t('Cancel'), class: 'button', click: function () {
                dialog.close();
              }}
            ],
            close: function () { container.remove(); }
          });

          dialog.showModal();
        };

        // Intercept both mouse and keyboard activations (Drupal AJAX often binds on mousedown).
        btn.addEventListener('click', intercept, {capture: true});
        btn.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') { intercept(e); }
        });
      });
    }
  };
})(Drupal, once, jQuery, drupalSettings);
