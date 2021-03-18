/**
 * @file
 * Media entity download link plugin.
 */

(function ($, Drupal, CKEDITOR) {

  'use strict';

  CKEDITOR.plugins.add('mediaentitydownload', {
    icons: 'mediaentitydownload',

    beforeInit: function (editor) {
      // Add the commands for link and unlink.
      editor.addCommand('mediaentitydownload', {
        allowedContent: {
          a: {
            attributes: {
              '!href': true
            },
            classes: {}
          }
        },
        requiredContent: new CKEDITOR.style({
          element: 'a',
          attributes: {
            href: ''
          }
        }),
        modes: {wysiwyg: 1},
        canUndo: true,
        exec: function (editor) {
          var saveCallback = function (links) {
            links.forEach(function (link, index) {
              editor.insertHtml('<a href="' + link.url + '">' + link.text + '</a>');
            });
          };
          var dialogSettings = {
            dialogClass: 'entity-select-dialog',
            resizable: false
          };
          var existingValues = {};
          Drupal.ckeditor.openDialog(editor, Drupal.url('media-entity-download/dialog/' + editor.config.drupal.format), existingValues, saveCallback, dialogSettings);
        }
      });

      // Add buttons for link and unlink.
      if (editor.ui.addButton) {
        editor.ui.addButton('MediaEntityDownload', {
          label: Drupal.t('Download Link'),
          command: 'mediaentitydownload'
        });
      }
    }
  });

})(jQuery, Drupal, CKEDITOR);
