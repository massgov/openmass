/**
 * Opens the Message box configuration dialog without scrolling the page.
 *
 * @param {string} url
 *   Dialog URL.
 * @param {object} editorObject
 *   Values passed as editor_object to the dialog.
 * @param {function} saveCallback
 *   Called with saved values on editor:dialogsave.
 * @param {object} dialogSettings
 *   jQuery UI dialog settings.
 */
let scrollLockState = null;

/**
 * Returns TRUE when CKEditor is inside another open dialog (e.g. Layout Paragraphs).
 */
function isNestedEditorDialogContext() {
  const dialogs = document.querySelectorAll('.ui-dialog');
  for (const dialog of dialogs) {
    if (dialog.offsetParent !== null && dialog.querySelector('.ck-editor')) {
      return true;
    }
  }
  const offCanvas = document.getElementById('drupal-off-canvas');
  return !!(offCanvas && offCanvas.offsetParent !== null && offCanvas.querySelector('.ck-editor'));
}

function lockPageScroll() {
  if (scrollLockState) {
    return;
  }
  const y = window.scrollY;
  scrollLockState = {x: window.scrollX, y};
  document.documentElement.style.scrollBehavior = 'auto';
  document.body.style.position = 'fixed';
  document.body.style.top = `-${y}px`;
  document.body.style.left = '0';
  document.body.style.right = '0';
  document.body.style.width = '100%';
}

function unlockPageScroll() {
  if (!scrollLockState) {
    return;
  }
  const {x, y} = scrollLockState;
  document.body.style.position = '';
  document.body.style.top = '';
  document.body.style.left = '';
  document.body.style.right = '';
  document.body.style.width = '';
  document.documentElement.style.scrollBehavior = '';
  window.scrollTo(x, y);
  scrollLockState = null;
}

export function openMassInlineMessageDialog(url, editorObject, saveCallback, dialogSettings) {
  const nestedDialog = isNestedEditorDialogContext();

  if (!nestedDialog) {
    lockPageScroll();
  }

  const unlock = () => {
    if (!nestedDialog) {
      unlockPageScroll();
    }
    window.removeEventListener('dialog:afterclose', unlock);
    document.removeEventListener('editor:dialogsave', unlock);
  };

  window.addEventListener('dialog:afterclose', unlock);
  document.addEventListener('editor:dialogsave', unlock, {once: true});

  const restoreScrollAfterDialog = () => {
    if (scrollLockState) {
      document.body.style.top = `-${scrollLockState.y}px`;
    }
    const dialogContent = document.querySelector(
      '.ui-dialog:has(#mass-inline-message-dialog-form) .ui-dialog-content',
    ) || document.querySelector('#mass-inline-message-dialog-form')?.closest('.ui-dialog-content');
    if (dialogContent && window.Drupal && window.Drupal.attachBehaviors) {
      window.Drupal.attachBehaviors(dialogContent, window.drupalSettings);
    }
  };

  window.addEventListener('dialog:aftercreate', restoreScrollAfterDialog, {once: true});
  if (window.jQuery) {
    window.jQuery(document).one('ajaxComplete.massInlineMessageOpen', () => {
      if (document.querySelector('#mass-inline-message-dialog-form')) {
        restoreScrollAfterDialog();
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
  dialogSettings.width = dialogSettings.width || 600;

  // Match core CKEditor dialog loading when nested inside Layout Paragraphs modals.
  const progressType = nestedDialog ? 'fullscreen' : 'throbber';

  const ckeditorAjaxDialog = Drupal.ajax({
    dialog: dialogSettings,
    dialogType: 'modal',
    selector: '.ckeditor5-dialog-loading-link',
    url,
    progress: {type: progressType},
    submit: {
      editor_object: editorObject,
    },
  });
  ckeditorAjaxDialog.execute();
  Drupal.ckeditor5.saveCallback = saveCallback;
}
