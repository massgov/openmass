/**
 * @file
 */
(function ($, Drupal, CKEDITOR) {

  'use strict';

  /**
   * Change tool tip text for "Download Link" provided by media_entity_download js.
   */

  if (typeof CKEDITOR != 'undefined') {
    CKEDITOR.config.contentsLangDirection = 'auto';
    CKEDITOR.on('instanceReady', function (e) {
      if (e.editor.contextMenu) {
        e.editor.removeMenuItem('tablecell_merge');
        e.editor.removeMenuItem('tablecell_merge_right');
        e.editor.removeMenuItem('tablecell_merge_down');
      }
    });
  }

})(jQuery, Drupal);
