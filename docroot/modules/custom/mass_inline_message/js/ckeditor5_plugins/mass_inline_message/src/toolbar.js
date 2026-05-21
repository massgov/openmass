import { Plugin } from 'ckeditor5/src/core';
import { WidgetToolbarRepository } from 'ckeditor5/src/widget';
import { ButtonView } from 'ckeditor5/src/ui';
import {
  getClosestSelectedMassInlineMessageElement,
  getClosestSelectedMassInlineMessageWidget,
  getMassInlineMessageModelFromViewTarget,
  openMassInlineMessageEditDialog,
} from './utils';

export default class MassInlineMessageToolbar extends Plugin {

  static get requires() {
    return [WidgetToolbarRepository];
  }

  init() {
    const editor = this.editor;
    const options = editor.config.get('massInlineMessage');
    if (!options) {
      return;
    }

    editor.ui.componentFactory.add('massInlineMessageEdit', (locale) => {
      const buttonView = new ButtonView(locale);
      buttonView.set({
        label: editor.t('Edit message box'),
        icon: '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M14.5 2.5l3 3L6 17H3v-3L14.5 2.5z"/></svg>',
        tooltip: true,
      });

      this.listenTo(buttonView, 'execute', () => {
        const modelElement = getClosestSelectedMassInlineMessageElement(
          editor.model.document.selection,
        );
        if (modelElement) {
          openMassInlineMessageEditDialog(editor, modelElement);
        }
      });

      return buttonView;
    });
  }

  afterInit() {
    this._registerWidgetToolbar();
    this._bindDomEditHandlers();
  }

  _registerWidgetToolbar() {
    const { editor } = this;
    if (!editor.plugins.has('WidgetToolbarRepository')) {
      return;
    }
    const widgetToolbarRepository = editor.plugins.get('WidgetToolbarRepository');
    widgetToolbarRepository.register('massInlineMessage', {
      ariaLabel: Drupal.t('Message box toolbar'),
      items: editor.config.get('massInlineMessage.toolbar') || ['massInlineMessageEdit'],
      getRelatedElement: (selection) => getClosestSelectedMassInlineMessageWidget(selection),
    });
  }

  _bindDomEditHandlers() {
    const { editor } = this;
    const domRoot = editor.editing.view.getDomRoot();
    if (!domRoot) {
      return;
    }

    const openEditorForTarget = (domTarget, domEvent) => {
      const modelElement = getMassInlineMessageModelFromViewTarget(editor, domTarget);
      if (!modelElement) {
        return;
      }
      domEvent.preventDefault();
      domEvent.stopPropagation();
      editor.model.change((writer) => {
        writer.setSelection(modelElement, 'on');
      });
      openMassInlineMessageEditDialog(editor, modelElement);
    };

    domRoot.addEventListener('dblclick', (domEvent) => {
      openEditorForTarget(domEvent.target, domEvent);
    }, true);

    domRoot.addEventListener('contextmenu', (domEvent) => {
      openEditorForTarget(domEvent.target, domEvent);
    }, true);
  }

}
