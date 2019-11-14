/**
 * @file
 * Adds clientside functionality and validation to the Topic Page Node Edit Form.
 */

(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.massValidationOrgPageNodeEditForm = {

    attach: function (context) {
      var $type = $('[name="field_topic_type"]', context);
      var $lede = $('[name="field_topic_lede[0][value]"]', context);

      // Add the required marker now, since we don't need to toggle that.
      $('label[for="' + $lede.attr('id') + '"]', context)
        .addClass('form-required')
        .addClass('js-form-required');

      // This callback toggles the visibility of the lede field based on
      // a provided type value.
      function setVisibility(subtype) {
        if (subtype === 'section landing') {
          $lede.closest('.js-form-wrapper').addClass('js-hide');
        }
        else {
          $lede.closest('.js-form-wrapper').removeClass('js-hide');
        }
      }

      // Bind a change handler to toggle visibility.
      $type.change(function (e) {
        setVisibility(e.target.value);
      });
      // Set the initial visibility.
      setVisibility($type.val());
    }
  };

})(jQuery, Drupal);
