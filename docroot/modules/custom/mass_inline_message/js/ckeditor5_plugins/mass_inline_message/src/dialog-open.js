/**
 * @file
 * Opens the Message box dialog from CKEditor (Ajax modal + save callback).
 *
 * Works with mass-inline-message-dialog.js: this file starts the dialog;
 * the behavior file wires Save/Cancel clicks inside the modal.
 */
import { refreshAllCkeditor5Viewports } from './viewport';

/**
 * Dedicated modal wrapper so nested editor dialogs can use #drupal-modal.
 */
function ensureMassInlineMessageModalContainer() {
  if (document.getElementById('mass-inline-message-modal')) {
    return;
  }
  const container = document.createElement('div');
  container.id = 'mass-inline-message-modal';
  container.className = 'ui-front';
  container.style.display = 'none';
  document.body.appendChild(container);
}

/**
 * Preserve Message box save callback when nested embed/media dialogs close.
 */
function getMessageBoxEditorDialogSaveHandler() {
  if (window.MassInlineMessageDialog && window.MassInlineMessageDialog.editorDialogSaveHandler) {
    return window.MassInlineMessageDialog.editorDialogSaveHandler;
  }
  return (values) => {
    if (window.MassInlineMessageDialog && window.MassInlineMessageDialog.invokeSaveCallback) {
      window.MassInlineMessageDialog.invokeSaveCallback(values);
    }
  };
}

function bindMessageBoxDialogLifecycle(saveCallback) {
  window.__massInlineMessageSaveCallback = saveCallback;
  Drupal.ckeditor5.saveCallback = getMessageBoxEditorDialogSaveHandler();

  const restoreSaveCallback = () => {
    if (
      document.querySelector('#mass-inline-message-dialog-form') &&
      window.__massInlineMessageSaveCallback
    ) {
      Drupal.ckeditor5.saveCallback = getMessageBoxEditorDialogSaveHandler();
    }
  };
  window.addEventListener('dialog:afterclose', restoreSaveCallback);

  const onMessageBoxClose = () => {
    if (document.querySelector('#mass-inline-message-dialog-form')) {
      return;
    }
    window.removeEventListener('dialog:afterclose', restoreSaveCallback);
    window.removeEventListener('dialog:afterclose', onMessageBoxClose);

    // Do not clear __massInlineMessageSaveCallback here. Core CKEditor clears
    // Drupal.ckeditor5.saveCallback on dialog:afterclose before the Message
    // box Ajax response returns; mass-inline-message-dialog.js applies the
    // saved values via editor:dialogsave and Ajax backup handlers instead.

    window.requestAnimationFrame(() => {
      refreshAllCkeditor5Viewports();
    });
  };
  window.addEventListener('dialog:afterclose', onMessageBoxClose);
}

export function openMassInlineMessageDialog(url, editorObject, saveCallback, dialogSettings) {
  ensureMassInlineMessageModalContainer();

  bindMessageBoxDialogLifecycle(saveCallback);

  const attachDialogBehaviors = () => {
    const dialogContent = document.querySelector('#mass-inline-message-dialog-form')
      ?.closest('.ui-dialog-content')
      || document.querySelector('#mass-inline-message-modal .ui-dialog-content');
    if (dialogContent && window.Drupal && window.Drupal.attachBehaviors) {
      window.Drupal.attachBehaviors(dialogContent, window.drupalSettings);
    }
  };

  window.addEventListener('dialog:aftercreate', attachDialogBehaviors, { once: true });
  if (window.jQuery) {
    window.jQuery(document).one('ajaxComplete.massInlineMessageOpen', () => {
      if (document.querySelector('#mass-inline-message-dialog-form')) {
        attachDialogBehaviors();
      }
    });
  }

  dialogSettings.classes = dialogSettings.classes || {};
  const uiDialogClasses = dialogSettings.classes['ui-dialog']
    ? dialogSettings.classes['ui-dialog'].split(' ')
    : [];
  if (dialogSettings.dialogClass) {
    uiDialogClasses.push(...dialogSettings.dialogClass.split(' '));
  }
  uiDialogClasses.push('ui-dialog--narrow', 'mass-inline-message-dialog');
  dialogSettings.classes['ui-dialog'] = uiDialogClasses.join(' ');
  dialogSettings.autoResize = window.matchMedia('(min-width: 600px)').matches;
  dialogSettings.width = dialogSettings.width || 'auto';

  // Fullscreen progress can block Layout Paragraphs modals; throbber is enough.
  const progressType = 'throbber';

  const ckeditorAjaxDialog = Drupal.ajax({
    dialog: dialogSettings,
    dialogType: 'modal',
    dialogRenderer: 'mass_inline_message',
    selector: '.ckeditor5-dialog-loading-link',
    url,
    progress: { type: progressType },
    submit: {
      editor_object: editorObject,
    },
  });
  ckeditorAjaxDialog.execute();
}
