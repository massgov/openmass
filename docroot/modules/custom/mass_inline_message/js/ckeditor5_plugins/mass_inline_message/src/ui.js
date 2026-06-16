import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import { openMassInlineMessageDialog } from './dialog-open';
import messageBoxIcon from '../theme/icons/message-box.svg';

export default class MassInlineMessageUI extends Plugin {

  init() {
    const editor = this.editor;
    const options = editor.config.get('massInlineMessage');
    if (!options) {
      return;
    }

    const { dialogSettings = {} } = options;
    const libraryURL = Drupal.url('mass-inline-message/dialog/' + options.format);

    editor.ui.componentFactory.add('messageBox', (locale) => {
      const command = editor.commands.get('insertMassInlineMessage');
      const buttonView = new ButtonView(locale);

      buttonView.set({
        label: Drupal.t('Message box'),
        icon: messageBoxIcon,
        tooltip: true,
      });

      buttonView.bind('isEnabled').to(command, 'isEnabled');

      this.listenTo(buttonView, 'execute', () => {
        const savedSelection = editor.model.createSelection(
          editor.model.document.selection,
        );

        openMassInlineMessageDialog(
          libraryURL,
          {},
          (values) => {
            editor.execute('insertMassInlineMessage', {
              attributes: values.attributes,
              body: values.body || '',
              selection: savedSelection,
            });
          },
          dialogSettings,
        );
      });

      return buttonView;
    });
  }

}
