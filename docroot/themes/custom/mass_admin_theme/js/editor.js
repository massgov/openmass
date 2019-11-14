/**
 * @file
 */
(function ($, Drupal) {

  'use strict';

  /**
   * Change tool tip text for "Download Link" provided by media_entity_download js.
   */
  Drupal.behaviors.editorTooltips = {
    attach: function (context, settings) {
      setTimeout(function () {
        $('.cke_button__mediaentitydownload').attr('title', Drupal.t('Insert Link to Document'));
      }, 1000);
    }
  };

})(jQuery, Drupal);
