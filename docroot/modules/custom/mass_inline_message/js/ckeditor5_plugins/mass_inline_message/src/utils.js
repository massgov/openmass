import { openMassInlineMessageDialog } from './dialog-open';
import { refreshEditorViewportAndToolbars } from './viewport';

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

  openMassInlineMessageDialog(libraryURL, existingValues, (values) => {
    editor.execute('insertMassInlineMessage', {
      attributes: values.attributes,
      body: values.body || '',
      replaceElement: modelElement,
    });

    editor.model.change((writer) => {
      writer.setSelection(modelElement, 'on');
    });
    editor.editing.view.focus();
    refreshEditorViewportAndToolbars(editor);
  }, dialogSettings);
}
