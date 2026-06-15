import { Plugin } from 'ckeditor5/src/core';
import { isWidget, WidgetToolbarRepository } from 'ckeditor5/src/widget';
import { ButtonView } from 'ckeditor5/src/ui';
import { openMassInlineMessageEditDialog } from './utils';
import {
  isEditorInsideDialog,
  isMessageBoxConfigDialogOpen,
  pinMassInlineMessageToolbarBalloon,
  refreshEditorViewportAndToolbars,
} from './viewport';

/**
 * @file
 * Message box widget toolbar (Edit button), same idea as entity_embed.
 */
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

    this._activeMassInlineMessage = null;

    editor.ui.componentFactory.add('massInlineMessageEdit', (locale) => {
      const buttonView = new ButtonView(locale);

      buttonView.set({
        label: editor.t('Edit'),
        icon: '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M14.5 2.5l3 3L6 17H3v-3L14.5 2.5z"/></svg>',
        tooltip: true,
      });

      this.listenTo(buttonView, 'execute', () => {
        const element = editor.model.document.selection.getSelectedElement()
          || this._activeMassInlineMessage;
        if (element?.name === 'massInlineMessage') {
          openMassInlineMessageEditDialog(editor, element);
        }
      });

      return buttonView;
    });
  }

  afterInit() {
    const { editor } = this;
    if (!editor.plugins.has('WidgetToolbarRepository')) {
      return;
    }

    const widgetToolbarRepository = editor.plugins.get(WidgetToolbarRepository);
    const options = editor.config.get('massInlineMessage') || {};

    widgetToolbarRepository.register('massInlineMessage', {
      ariaLabel: Drupal.t('Message box toolbar'),
      items: options.toolbar || ['massInlineMessageEdit'],
      getRelatedElement(selection) {
        const viewElement = selection.getSelectedElement();
        if (!viewElement || !isWidget(viewElement)) {
          return null;
        }
        if (!viewElement.getCustomProperty('massInlineMessage')) {
          return null;
        }

        return viewElement;
      },
    });

    editor.model.document.selection.on('change', () => {
      const selected = editor.model.document.selection.getSelectedElement();
      if (selected?.name === 'massInlineMessage') {
        this._activeMassInlineMessage = selected;
      }
    });

    this._bindDialogCloseRestore();

    if (isEditorInsideDialog(editor)) {
      this._bindModalToolbarPositioning();
    }
  }

  /**
   * Layout Paragraphs modals: keep Edit toolbar above the widget while scrolling.
   */
  _bindModalToolbarPositioning() {
    const { editor } = this;

    const reposition = () => {
      if (isMessageBoxConfigDialogOpen()) {
        return;
      }
      if (editor.model.document.selection.getSelectedElement()?.name !== 'massInlineMessage') {
        return;
      }
      refreshEditorViewportAndToolbars(editor);
    };

    const onScroll = () => reposition();
    document.addEventListener('scroll', onScroll, { capture: true, passive: true });

    const scrollParents = [];
    const domRoot = editor.editing.view.getDomRoot();
    if (domRoot) {
      let parent = domRoot.parentElement;
      while (parent && parent !== document.body) {
        parent.addEventListener('scroll', onScroll, { passive: true });
        scrollParents.push(parent);
        parent = parent.parentElement;
      }
    }

    this.listenTo(editor.ui, 'update', () => {
      if (isMessageBoxConfigDialogOpen()) {
        return;
      }
      if (editor.model.document.selection.getSelectedElement()?.name === 'massInlineMessage') {
        pinMassInlineMessageToolbarBalloon(editor);
      }
    }, { priority: 'lowest' });

    const dialog = domRoot?.closest('.ui-dialog');
    if (dialog) {
      dialog.addEventListener('dialogContentResize', onScroll);
    }

    this.listenTo(editor.editing.view.document, 'mousedown', (evt, data) => {
      const target = data.domTarget;
      if (!(target instanceof HTMLElement) || !target.closest('.ck-body-wrapper')) {
        return;
      }
      const modelElement = this._activeMassInlineMessage
        || editor.model.document.selection.getSelectedElement();
      if (modelElement?.name === 'massInlineMessage') {
        editor.model.change((writer) => {
          writer.setSelection(modelElement, 'on');
        });
      }
    }, { priority: 'high' });

    this.on('destroy', () => {
      document.removeEventListener('scroll', onScroll, { capture: true });
      scrollParents.forEach((parent) => {
        parent.removeEventListener('scroll', onScroll);
      });
      if (dialog) {
        dialog.removeEventListener('dialogContentResize', onScroll);
      }
    });
  }

  /**
   * After the Message box dialog closes, re-select the widget and refresh the toolbar.
   */
  _bindDialogCloseRestore() {
    const { editor } = this;

    const restore = () => {
      if (document.querySelector('#mass-inline-message-dialog-form')) {
        return;
      }

      const modelElement = this._activeMassInlineMessage
        || editor.model.document.selection.getSelectedElement();
      if (modelElement?.name !== 'massInlineMessage') {
        return;
      }

      editor.model.change((writer) => {
        writer.setSelection(modelElement, 'on');
      });
      editor.editing.view.focus();
      refreshEditorViewportAndToolbars(editor);
    };

    window.addEventListener('dialog:afterclose', restore);
    this.on('destroy', () => {
      window.removeEventListener('dialog:afterclose', restore);
    });
  }

  static get pluginName() {
    return 'MassInlineMessageToolbar';
  }

}
