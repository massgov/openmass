import { isWidget } from 'ckeditor5/src/widget';
import { openMassInlineMessageDialog } from './dialog-open';

/**
 * Checks if a view element is a Message box widget.
 */
export function isMassInlineMessageWidget(viewElement) {
  return (
    !!viewElement
    && isWidget(viewElement)
    && !!viewElement.getCustomProperty('massInlineMessage')
  );
}

/**
 * Gets the selected massInlineMessage model element, if any.
 */
export function getClosestSelectedMassInlineMessageElement(selection) {
  const selectedElement = selection.getSelectedElement();
  if (selectedElement && selectedElement.name === 'massInlineMessage') {
    return selectedElement;
  }
  const position = selection.getFirstPosition();
  if (!position) {
    return null;
  }
  return position.findAncestor('massInlineMessage');
}

/**
 * Gets the selected Message box widget view element, if any.
 */
export function getClosestSelectedMassInlineMessageWidget(selection) {
  const viewElement = selection.getSelectedElement();
  if (viewElement && isMassInlineMessageWidget(viewElement)) {
    return viewElement;
  }

  const position = selection.getFirstPosition();
  if (!position) {
    return null;
  }

  let parent = position.parent;
  while (parent) {
    if (parent.is('element') && isMassInlineMessageWidget(parent)) {
      return parent;
    }
    parent = parent.parent;
  }

  return null;
}

/**
 * Maps a DOM click target to the Message box model element.
 */
export function getMassInlineMessageModelFromViewTarget(editor, domTarget) {
  if (!(domTarget instanceof Element)) {
    return null;
  }

  const widgetDom = domTarget.closest('.mass-inline-message-ckeditor-widget');
  if (!widgetDom) {
    return null;
  }

  const view = editor.editing.view;
  const viewElement = view.domConverter.mapDomToView(widgetDom);
  if (!viewElement || !isMassInlineMessageWidget(viewElement)) {
    return null;
  }

  return editor.editing.mapper.toModelElement(viewElement);
}

/**
 * Opens the Message box dialog to edit an existing widget.
 */
export function openMassInlineMessageEditDialog(editor, modelElement) {
  const options = editor.config.get('massInlineMessage');
  if (!options || !modelElement || modelElement.name !== 'massInlineMessage') {
    return;
  }

  const libraryURL = Drupal.url('mass-inline-message/dialog/' + options.format);
  const { dialogSettings = {} } = options;
  const command = editor.commands.get('insertMassInlineMessage');
  const existingValues = {
    'data-title': modelElement.getAttribute('dataTitle') || '',
    'data-type': modelElement.getAttribute('dataType') || 'info',
    body: command.bodyStorage ? (command.bodyStorage.get(modelElement) || '') : '',
  };

  const savedSelection = editor.model.createSelection(
    editor.model.document.selection,
  );

  openMassInlineMessageDialog(libraryURL, existingValues, (values) => {
    editor.commands.get('insertMassInlineMessage')._insert({
      attributes: values.attributes,
      body: values.body || '',
      selection: savedSelection,
    });
    editor.editing.view.focus();
  }, dialogSettings);
}
