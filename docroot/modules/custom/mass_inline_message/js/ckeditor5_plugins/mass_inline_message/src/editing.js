import { Plugin } from 'ckeditor5/src/core';
import { Widget, toWidget } from 'ckeditor5/src/widget';
import InsertMassInlineMessageCommand from './command';
import { renderMayflowerPreview } from './preview';

/**
 * Extracts stored body HTML from a mass-inline-message view element.
 */
function extractBodyHtmlFromView(viewItem, editor) {
  let bodyHtml = '';
  for (const child of viewItem.getChildren()) {
    bodyHtml += editor.data.processor.toData(child);
  }
  bodyHtml = bodyHtml.trim();
  if (bodyHtml.match(/^<div[^>]*>[\s\S]*<\/div>$/i)) {
    const inner = bodyHtml.replace(/^<div[^>]*>([\s\S]*)<\/div>$/i, '$1').trim();
    if (inner) {
      bodyHtml = inner;
    }
  }
  return bodyHtml;
}

export default class MassInlineMessageEditing extends Plugin {

  static get requires() {
    return [Widget];
  }

  init() {
    this.bodyStorage = new WeakMap();
    this._defineSchema();
    this._defineConverters();
    const command = new InsertMassInlineMessageCommand(this.editor);
    command.bodyStorage = this.bodyStorage;
    this.editor.commands.add('insertMassInlineMessage', command);
  }

  _defineSchema() {
    const schema = this.editor.model.schema;
    schema.register('massInlineMessage', {
      isObject: true,
      isContent: true,
      isBlock: true,
      allowWhere: '$block',
      allowAttributes: ['dataTitle', 'dataType'],
    });
    this.editor.editing.view.domConverter.blockElements.push('mass-inline-message');
  }

  _defineConverters() {
    const { editor } = this;
    const { conversion } = editor;
    const bodyStorage = this.bodyStorage;

    conversion.for('upcast').elementToElement({
      view: {
        name: 'mass-inline-message',
        attributes: {
          'data-title': true,
          'data-type': true,
        },
      },
      model: (viewElement, { writer }) => {
        const modelElement = writer.createElement('massInlineMessage', {
          dataTitle: viewElement.getAttribute('data-title') || '',
          dataType: viewElement.getAttribute('data-type') || 'info',
        });
        bodyStorage.set(modelElement, extractBodyHtmlFromView(viewElement, editor));
        return modelElement;
      },
      converterPriority: 'high',
    });

    conversion.for('dataDowncast').elementToElement({
      model: 'massInlineMessage',
      view: (modelElement, { writer }) => {
        const container = writer.createContainerElement('mass-inline-message', {
          'data-title': modelElement.getAttribute('dataTitle') || '',
          'data-type': modelElement.getAttribute('dataType') || 'info',
        });
        const bodyHtml = bodyStorage.get(modelElement);
        if (bodyHtml) {
          const bodyElement = writer.createRawElement('div', {}, (domElement) => {
            domElement.innerHTML = bodyHtml;
          });
          writer.insert(writer.createPositionAt(container, 0), bodyElement);
        }
        return container;
      },
      converterPriority: 'high',
    });

    conversion.for('editingDowncast').elementToElement({
      model: 'massInlineMessage',
      view: (modelElement, { writer }) => {
        const title = modelElement.getAttribute('dataTitle') || '';
        const type = modelElement.getAttribute('dataType') || 'info';
        const typeLabel = type === 'warning' ? Drupal.t('Alert') : Drupal.t('Informational');
        const bodyHtml = bodyStorage.get(modelElement) || '';
        const previewConfig = editor.config.get('massInlineMessage');

        const container = writer.createContainerElement('div', {
          class: 'mass-inline-message-ckeditor-widget',
        });
        writer.setCustomProperty('massInlineMessage', true, container);

        const previewHost = writer.createRawElement(
          'div',
          { class: 'mass-inline-message-ckeditor-widget__preview-host' },
          (domElement) => {
            renderMayflowerPreview(domElement, previewConfig, {
              title,
              type,
              body: bodyHtml,
            });
          },
        );
        writer.insert(writer.createPositionAt(container, 0), previewHost);

        return toWidget(container, writer, {
          label: `${Drupal.t('Message box')}: ${title || Drupal.t('(No title)')} (${typeLabel})`,
        });
      },
    });
  }

}
