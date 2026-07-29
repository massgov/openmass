/**
 * @file
 * Keeps the widget Edit toolbar positioned above the box inside LP modals.
 */
export function refreshAllCkeditor5Viewports() {
  if (typeof Drupal === 'undefined' || !Drupal.CKEditor5Instances) {
    return;
  }

  Drupal.CKEditor5Instances.forEach((editor) => {
    refreshEditorViewportAndToolbars(editor);
  });
}

/**
 * Whether the Message box configuration dialog is open.
 */
export function isMessageBoxConfigDialogOpen() {
  return !!document.querySelector('#mass-inline-message-dialog-form');
}

/**
 * Finds the visible Message box widget Edit toolbar balloon.
 *
 * Do not match generic ck-toolbar-container panels (table, image, link, etc.).
 */
export function getMassInlineMessageToolbarBalloon() {
  const panels = document.querySelectorAll(
    '.ck-body-wrapper .ck-balloon-panel.ck-balloon-panel_visible',
  );

  for (const panel of panels) {
    if (panel.classList.contains('ck-powered-by-balloon')) {
      continue;
    }
    if (panel.querySelector(
      '.ck-button[data-cke-tooltip-text="Edit"], .ck-button[aria-label="Edit"]',
    )) {
      return panel;
    }
  }

  return null;
}

/**
 * Returns TRUE when the editor lives inside an open jQuery UI dialog.
 */
export function isEditorInsideDialog(editor) {
  const domRoot = editor?.editing?.view?.getDomRoot?.();
  return !!(domRoot && domRoot.closest('.ui-dialog'));
}

/**
 * Pins the Message box widget toolbar balloon above the widget in modal editors.
 */
export function pinMassInlineMessageToolbarBalloon(editor, retriesLeft = 2) {
  if (!isEditorInsideDialog(editor) || isMessageBoxConfigDialogOpen()) {
    return;
  }

  const viewElement = editor.editing.view.document.selection.getSelectedElement();
  if (!viewElement?.getCustomProperty('massInlineMessage')) {
    return;
  }

  const widgetDom = editor.editing.view.domConverter.mapViewToDom(viewElement);
  if (!widgetDom) {
    return;
  }

  const balloon = getMassInlineMessageToolbarBalloon();
  if (!balloon) {
    if (retriesLeft > 0) {
      window.requestAnimationFrame(() => {
        pinMassInlineMessageToolbarBalloon(editor, retriesLeft - 1);
      });
    }
    return;
  }

  const widgetRect = widgetDom.getBoundingClientRect();
  const balloonRect = balloon.getBoundingClientRect();
  const top = widgetRect.top - balloonRect.height - 8;
  const left = widgetRect.left + Math.max(0, (widgetRect.width - balloonRect.width) / 2);

  balloon.style.position = 'fixed';
  balloon.style.top = `${Math.max(8, top)}px`;
  balloon.style.left = `${Math.max(8, left)}px`;
  balloon.style.right = 'auto';
  balloon.style.bottom = 'auto';
  balloon.style.zIndex = '100055';
}

/**
 * Refreshes CKEditor viewport offsets and re-pins the widget toolbar in dialogs.
 */
export function refreshEditorViewportAndToolbars(editor) {
  if (
    typeof jQuery !== 'undefined'
    && typeof Drupal !== 'undefined'
    && Drupal.displace
  ) {
    jQuery(document).trigger('drupalViewportOffsetChange', [Drupal.displace.offsets]);
  }

  if (editor?.ui) {
    editor.ui.update();
  }

  if (isEditorInsideDialog(editor)) {
    window.requestAnimationFrame(() => {
      pinMassInlineMessageToolbarBalloon(editor);
    });
  }
}
