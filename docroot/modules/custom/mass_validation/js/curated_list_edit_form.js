/**
 * @file
 * Adds clientside conditional logic to the Curated List node edit form.
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.massValidationCuratedListNodeEditForm = {
    attach: function (context, settings) {
      $('.field--name-field-curatedlist-list-section table.field-multiple-table tr .paragraphs-subform', context).each(function () {
        var parentThis = this;
        $('.field--name-field-type-of-list-content select', this).on('change', function () {
          if ($(this).val() === 'contact') {
            $('.field--name-field-contact-values-to-display', parentThis)
              .removeClass('js-hide');
          }
          else {
            $('.field--name-field-contact-values-to-display', parentThis)
              .addClass('js-hide');
          }
        });
        $('.field--name-field-type-of-list-content select', this).trigger('change');
      });
    }
  };

})(jQuery, Drupal);
