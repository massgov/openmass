/**
 * @file
 * Adds clientside functionality and validation to the Org Page Node Edit Form.
 */

(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.massValidationBoardMember = {
    attach: function (context, settings) {
      // If board member position is checked as vacant, hide person reference field.
      $('.field--name-field-position-is-vacant', context).each(function () {
        $('input', this).on('change', function () {
          var input = this;
          var $fieldWrapper = $(input).closest('.field--name-field-position-is-vacant');
          if (input.checked) {
            $fieldWrapper.siblings('.field--name-field-person').addClass('js-hide');
          }
          else {
            $fieldWrapper.siblings('.field--name-field-person').removeClass('js-hide');
          }
        });
        $('input', this).trigger('change');
      });

      // Hide the heading and description fields if the toggle is checked.
      $('.field--name-field-add-heading-description', context).each(function () {
        var toggle = this;
        $('input', this).on('change', function () {
          var input = this;
          if (input.checked) {
            $(toggle).siblings('.field--name-field-heading, .field--name-field-description').removeClass('js-hide');
          }
          else {
            $(toggle).siblings('.field--name-field-heading, .field--name-field-description').addClass('js-hide');
          }
        });
        $('input', this).trigger('change');
      });
    }
  };

  Drupal.behaviors.massValidationOrgPageNodeEditForm = {
    $conditionalTabs: {},
    $electedRequired: {},
    $generalRequired: {},
    $generalRequiredTabs: {},
    $conditionallyRequiredFields: {},
    attach: function (context, settings) {
      var self = this;

      this.initVars(context);
      // Update the form based on the value of the Subtype field.
      self.updateElements(context);

      $('#edit-field-subtype', context).on('change', function () {
        self.updateElements(context);
      });
    },

    initVars: function (context) {
      var $tabs = $('.horizontal-tab-button', context);
      this.$conditionalTabs = $tabs
        .find('a[href$="about-details-tab"]')
        .closest('li');

      // Allow a simple array of machine field names to be used to calculate the selector
      // for conditionally required fields.
      var electedRequiredFields = [
        'field_person_bio'
      ].map(function (field) {
        return '.field--name-' + field.replace(/_/g, '-').replace(/--/g, '__') + ' label';
      }).join(', ');

      this.$electedRequired = $(electedRequiredFields, context);
      this.$generalRequiredTabs = $tabs.find('a[href$="edit-group-actions"]').find('strong');
    },

    updateElements: function (context) {
      var subtype = $('#edit-field-subtype option:selected', context).val();

      if (typeof this.$conditionallyRequiredFields.addClass === 'function') {
        this.$conditionallyRequiredFields.addClass('form-required');
      }

      if (subtype === 'General Organization') {
        this.$conditionalTabs.addClass('js-hide');
        if (typeof this.$generalRequired.addClass === 'function') {
          this.$generalRequired.addClass('form-required');
        }
        this.$electedRequired.removeClass('form-required');
        this.$generalRequiredTabs.addClass('form-required');
        $(".field--name-field-organization-sections details.section-content .field--name-field-section-long-form-content input[id*='-subform-field-section-long-form-content-add-more-add-more-button-list-board-members']").addClass('js-hide');
      }
      else if (subtype === 'Boards') {
        this.$conditionalTabs.addClass('js-hide');
        $(".field--name-field-organization-sections details.section-content .field--name-field-section-long-form-content input[id*='-subform-field-section-long-form-content-add-more-add-more-button-list-board-members']").removeClass('js-hide');
      }
      else {
        this.$conditionalTabs.removeClass('js-hide');
        this.$generalRequired.removeClass('form-required');
        this.$electedRequired.addClass('form-required');
        this.$generalRequiredTabs.removeClass('form-required');
        $(".field--name-field-organization-sections details.section-content .field--name-field-section-long-form-content input[id*='-subform-field-section-long-form-content-add-more-add-more-button-list-board-members']").addClass('js-hide');
      }
    }
  };

})(jQuery, Drupal);
