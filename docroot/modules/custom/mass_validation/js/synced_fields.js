/**
 * @file
 * Adds clientside functionality and validation to the Org Page Node Edit Form.
 */

(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.massValidationSyncedFields = {
    $source: {},
    $destination: {},
    sourceField: '',

    attach: function (context, settings) {
      var self = this;
      // This setting is passed in from mass_validation.module.
      if (settings.massValidation.sourceField) {
        this.sourceField = settings.massValidation.sourceField;

        // Run sync elements any time the page loads or new source elements are added.
        this.syncElements(context);

        // For each input form element in the source, run syncElements every time something is entered
        // or for any autocomplete selection.
        this.$source.each(function () {
          if (this.type === 'text') {
            var $self = $(this);
            $self.on('input', function () {
              self.syncElements(context);
            });
            $self.on('autocompleteclose', function (event, ui) {
              $(this).trigger('input');
            });
          }
        });
      }
    },

    // Initialize or reset the source and destination fields.
    initVars: function (context) {
      // Get the source from the field passed in, but don't get the add buttons or the values if they
      // reference a person node. Only get Organizations. Note that the data-bundle property is being
      // added in mass_validation_form_alter.
      this.$source = $('input[name*="' + this.sourceField + '"]', context)
        .not('[name*="add_"]')
        .not('[data-bundle="person"]');
      $('input[name^="field_organizations_add"]', context).remove();
      // Set the destination to the field wrapper.
      this.$destination = $('#edit-field-organizations-wrapper', context);
      // Remove all but the first row (field description text).
      this.$destination.find('tbody').find('tr').not(':first').remove();
      // Remove the button to show weights since it is no longer valid.
      this.$destination.find('button').remove();
      // Add asterisk to the organizations field.
      this.$destination.find('.label').addClass('form-required');
    },

    // Add a row to the destination for each value of the correct type in the source.
    syncElements: function (context) {
      // Run initVars to clear the previous destination rows and make sure we have all of the source values.
      this.initVars(context);
      // For each element in the source field, create a row for the destination field area.
      var self = this;
      this.$source.each(function (index, element) {
        var value = self.$source[index].value;
        if (value.length > 0 && value.indexOf('- Person') === -1) {
          self.$destination.find('tbody').append('<tr><td>' + self.$source[index].value + '</td></tr>');
        }
      });
    }
  };

})(jQuery, Drupal);
