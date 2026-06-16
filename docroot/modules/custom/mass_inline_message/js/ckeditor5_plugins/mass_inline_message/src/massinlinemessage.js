import { Plugin } from 'ckeditor5/src/core';
import MassInlineMessageEditing from './editing';
import MassInlineMessageUI from './ui';
import MassInlineMessageToolbar from './toolbar';

export default class MassInlineMessage extends Plugin {

  static get requires() {
    return [
      MassInlineMessageEditing,
      MassInlineMessageUI,
      MassInlineMessageToolbar,
    ];
  }

  static get pluginName() {
    return 'MassInlineMessage';
  }

}
