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

  execute({ attributes, body, selection }) {
    this._insert({ attributes, body, selection });
  }

  /**
   * Inserts or replaces a message box (not gated by isEnabled).
   *
   * @param {object} options
   *   attributes, body, and optional selection captured before the dialog.
   */
  _insert({ attributes, body, selection }) {
    const editor = this.editor;
    const insertSelection = selection || editor.model.document.selection;

    editor.model.change((writer) => {
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

      const selected = insertSelection.getSelectedElement();
      if (selected && selected.name === 'massInlineMessage' && selected.parent) {
        writer.insert(messageBox, writer.createPositionBefore(selected));
        writer.remove(selected);
        writer.setSelection(messageBox, 'on');
        return;
      }

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
