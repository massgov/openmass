import { Plugin } from 'ckeditor5/src/core';
import { Widget, toWidget } from 'ckeditor5/src/widget';
import InsertMassInlineMessageCommand from './command';
import { renderMayflowerPreview } from './preview';

/**
 * Extracts stored body HTML from a mass-inline-message view element.
 */
function extractBodyHtmlFromView(viewItem, editor) {
  const rawContent = viewItem.getCustomProperty && viewItem.getCustomProperty('$rawContent');
  if (typeof rawContent === 'string') {
    return rawContent.trim();
  }

  let bodyHtml = '';
  for (const child of viewItem.getChildren()) {
    if (child.is && child.is('$text')) {
      bodyHtml += child.data || '';
      continue;
    }
    try {
      bodyHtml += editor.data.processor.toData(child);
    }
    catch (error) {
      // Ignore nodes that cannot be serialized by the current editor build.
    }
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

  static get pluginName() {
    return 'MassInlineMessageEditing';
  }

  /**
   * Re-renders the Mayflower preview for an existing widget in the editing view.
   */
  refreshWidgetPreview(modelElement) {
    if (!modelElement || modelElement.name !== 'massInlineMessage') {
      return;
    }

    const viewElement = this.editor.editing.mapper.toViewElement(modelElement);
    if (!viewElement) {
      return;
    }

    const domElement = this.editor.editing.view.domConverter.mapViewToDom(viewElement);
    if (!domElement) {
      return;
    }

    const previewHost = domElement.querySelector('.mass-inline-message-ckeditor-widget__preview-host');
    if (!previewHost) {
      return;
    }

    const previewConfig = this.editor.config.get('massInlineMessage');
    renderMayflowerPreview(previewHost, previewConfig, {
      title: modelElement.getAttribute('dataTitle') || '',
      type: modelElement.getAttribute('dataType') || 'info',
      body: this.bodyStorage.get(modelElement) || '',
    });
  }

  init() {
    if (this.editor.data && this.editor.data.registerRawContentMatcher) {
      this.editor.data.registerRawContentMatcher({
        name: 'mass-inline-message',
      });
    }
    else if (
      this.editor.data &&
      this.editor.data.processor &&
      this.editor.data.processor.registerRawContentMatcher
    ) {
      this.editor.data.processor.registerRawContentMatcher({
        name: 'mass-inline-message',
      });
    }

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
    this.editor.editing.view.domConverter.blockElements.push('figure');
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

        const container = writer.createContainerElement('figure', {
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
