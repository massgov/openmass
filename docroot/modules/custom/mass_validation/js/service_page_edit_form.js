/**
 * @file
 * Adds clientside functionality and validation to the Service Page Node Edit Form.
 */

(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.massValidationServicePageNodeEditForm = {
    $conditionallyRequiredFields: {},
    $conditionallyHide: {},
    $conditionalFeaturedHelpText: {},

    attach: function (context, settings) {
      var self = this;

      this.initVars(context);
      // Update the form based on the value of the Subtype field.
      self.updateElements(context);
      // Attach event listener to template field.
      $('#edit-field-template', context).on('change', function () {
        self.updateElements(context);
      });
      // Check if the targeted div exists before checking for the Two Column field.
      if ($('[data-drupal-selector*="-subform-field-two-column-wrapper"]', context).length) {
        self.handleCheckboxToggle(context);
      }
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

      var conditionalFeaturedHelpText = '<td class="tabledrag-has-colspan custom-service-description" colspan="3"><span class="service-help-text">Tasks will be prominently displayed both on this page and when the service is shown on Topic pages.<br><br><span class="service-help-text">We recommend each <strong>Featured task</strong> also typically be included among your links under individual Link groups to ensure those resources can be found in context.</span></span></td>';
      this.$conditionalFeaturedHelpText = $(conditionalFeaturedHelpText, context);
      this.$conditionallyRequiredFields = $(conditionallyRequiredFields, context);
      this.$conditionallyHide = $(conditionallyHide, context);
    },

    updateElements: function (context) {
      var template = $('#edit-field-template input:checked', context).val();
      if (template === 'custom') {
        if ($('.field--name-field-service-ref-actions-2 table tbody tr:first-child .custom-service-description', context).length) {
          $('.field--name-field-service-ref-actions-2 table tbody tr:first-child .custom-service-description', context).show();
          $('.field--name-field-service-ref-actions-2 table tbody tr:first-child .mass-description', context).hide();
        }
        else {
          $('.field--name-field-service-ref-actions-2 table tbody tr:first-child .mass-description', context).after(this.$conditionalFeaturedHelpText);
          $('.field--name-field-service-ref-actions-2 table tbody tr:first-child .mass-description', context).hide();
        }
        this.$conditionallyRequiredFields.addClass('form-required');
        this.$conditionallyHide.addClass('js-hide');
      }
      else {
        if ($('.field--name-field-service-ref-actions-2 table .custom-service-description', context).length) {
          $('.field--name-field-service-ref-actions-2 table .custom-service-description', context).hide();
          $('.field--name-field-service-ref-actions-2 table .mass-description', context).show();
        }
        this.$conditionallyRequiredFields.removeClass('form-required');
        this.$conditionallyHide.removeClass('js-hide');
      }
    },

    // Add toggle functionality to the Two Column field for hiding/showing the Subtitle and Service Section Link fields.
    handleCheckboxToggle: function (context) {
      $('[data-drupal-selector*="-subform-field-two-column-wrapper"]', context).each(function () {
        var $wrapper = $(this);
        var $checkbox = $wrapper.find('input[type="checkbox"]');
        var $subtitleWrapper = $wrapper.siblings('[data-drupal-selector*="-subform-field-subtitle-wrapper"]');
        var $serviceSectionWrapper = $wrapper.siblings('[data-drupal-selector*="-subform-field-service-section-link-wrapper"]');

        function toggleVisibility() {
          if ($checkbox.is(':checked')) {
            $subtitleWrapper.show();
            $serviceSectionWrapper.show();
          }
          else {
            $subtitleWrapper.hide();
            $serviceSectionWrapper.hide();
          }
        }

        toggleVisibility();
      });

      // Toggle the visibility of the Subtitle and Service Section Link fields.
      $('[data-drupal-selector*="-subform-field-two-column-wrapper"] input[type="checkbox"]', context).on('change', function () {
        var $checkbox = $(this);
        var $wrapper = $checkbox.closest('[data-drupal-selector*="-subform-field-two-column-wrapper"]');
        var $subtitleWrapper = $wrapper.siblings('[data-drupal-selector*="-subform-field-subtitle-wrapper"]');
        var $serviceSectionWrapper = $wrapper.siblings('[data-drupal-selector*="-subform-field-service-section-link-wrapper"]');

        if ($checkbox.is(':checked')) {
          $subtitleWrapper.show();
          $serviceSectionWrapper.show();
        }
        else {
          $subtitleWrapper.hide();
          $serviceSectionWrapper.hide();
        }
      });
    }
  };

})(jQuery, Drupal);
