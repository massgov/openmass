/**
 * @file
 * Adds clientside functionality and validation to the Service Page Node Edit Form.
 */

(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.massValidationServicePageNodeEditForm = {
    $conditionallyRequiredFields: {},
    $conditionallyHide: {},

    attach: function (context, settings) {
      var self = this;

      this.initVars(context);
      // Update the form based on the value of the Subtype field.
      self.updateElements(context);

      $('#edit-field-template', context).on('change', function () {
        self.updateElements(context);
      });
    },

    initVars: function (context) {
      var conditionallyRequiredFields = [
        '.field--name-field-link-group > div > div > strong',
        '.field--name-field-link-group thead .label'
      ].join(', ');

      var conditionallyHide = [
        '#edit-group-tasks-key-info .seven-details__description',
        '#what-would-you-like-to-do legend',
        '#what-would-you-like-to-do--description',
        '#what-you-need-to-know',
        '#additional-resources'
      ].join(', ');

      this.$conditionallyRequiredFields = $(conditionallyRequiredFields, context);
      this.$conditionallyHide = $(conditionallyHide, context);
    },

    updateElements: function (context) {
      var template = $('#edit-field-template input:checked', context).val();
      if (template === 'custom') {
        this.$conditionallyRequiredFields.addClass('form-required');
        this.$conditionallyHide.addClass('js-hide');
      }
      else {
        this.$conditionallyRequiredFields.removeClass('form-required');
        this.$conditionallyHide.removeClass('js-hide');
      }
    }
  };

})(jQuery, Drupal);
