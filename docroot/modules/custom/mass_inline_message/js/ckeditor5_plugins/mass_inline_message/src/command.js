import { Command } from 'ckeditor5/src/core';

/**
 * Escapes HTML attribute values.
 */
export function escapeAttr(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

/**
 * Builds stored HTML for a message box.
 */
export function buildMessageBoxHtml(attributes, body) {
  const title = escapeAttr(attributes['data-title'] || '');
  const type = escapeAttr(attributes['data-type'] || 'info');
  const bodyHtml = body || '';
  return `<mass-inline-message data-title="${title}" data-type="${type}">${bodyHtml}</mass-inline-message>`;
}

export default class InsertMassInlineMessageCommand extends Command {

  execute({ attributes, body, selection, replaceElement }) {
    this._insert({ attributes, body, selection, replaceElement });
  }

  /**
   * Inserts or replaces a message box (not gated by isEnabled).
   *
   * @param {object} options
   *   attributes, body, optional selection, and optional replaceElement when
   *   saving from a dialog (selection is often lost on editor:dialogsave).
   */
  _insert({ attributes, body, selection, replaceElement }) {
    const editor = this.editor;
    const editingPlugin = editor.plugins.get('MassInlineMessageEditing');

    editor.model.change((writer) => {
      const existing = replaceElement
        || (selection || editor.model.document.selection).getSelectedElement();

      if (existing && existing.name === 'massInlineMessage' && existing.root) {
        writer.setAttribute('dataTitle', attributes['data-title'] || '', existing);
        writer.setAttribute('dataType', attributes['data-type'] || 'info', existing);

        if (this.bodyStorage) {
          if (body) {
            this.bodyStorage.set(existing, body);
          }
          else {
            this.bodyStorage.delete(existing);
          }
        }

        writer.setSelection(existing, 'on');
        editingPlugin.refreshWidgetPreview(existing);
        return;
      }

      const messageBox = writer.createElement('massInlineMessage', {
        dataTitle: attributes['data-title'] || '',
        dataType: attributes['data-type'] || 'info',
      });

      if (this.bodyStorage) {
        if (body) {
          this.bodyStorage.set(messageBox, body);
        }
        else {
          this.bodyStorage.delete(messageBox);
        }
      }

      const insertSelection = selection || editor.model.document.selection;
      editor.model.insertContent(messageBox, insertSelection);
      writer.setSelection(messageBox, 'on');
    });
  }

  refresh() {
    const model = this.editor.model;
    const position = model.document.selection.getFirstPosition();
    if (!position) {
      this.isEnabled = false;
      return;
    }
    this.isEnabled = model.schema.findAllowedParent(position, 'massInlineMessage') !== null;
  }

}
