/**
 * @file
 */
(function ($, Drupal) {

  'use strict';

  /**
   * Restore paragraph button text in curated_list and alert edit pages to work around bug
   * with conditional_fields module; see DP-10239, DP-9791.
   */
  Drupal.behaviors.paragraphButtons = {
    attach: function (context, settings) {
      var $buttons = $('#edit-field-curatedlist-list-section-add-more-add-more-button-list-dynamic')
        .add($('#edit-field-curatedlist-list-section-add-more-add-more-button-list-static'))
        .add($('#edit-field-list-directory-section-add-more-add-more-button-list-manual-directory'))
        .add($('#edit-field-list-directory-section-add-more-add-more-button-list-dynamic'))
        .add($('input[id^=edit-field-target-pages-para-ref]'));

      $buttons.each(function () {
        $(this).data('initial-value', $(this).val());
      });
      $('#edit-field-list-type,[name=field_alert_display]').change(function () {
        $buttons.each(function () {
          $(this).val($(this).data('initial-value'));
        });
      });
    }
  };

})(jQuery, Drupal);
