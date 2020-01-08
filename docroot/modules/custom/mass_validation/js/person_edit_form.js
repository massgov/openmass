/**
 * @file
 * Adds clientside functionality and validation to the Person node edit form.
 */

(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.massValidationPersonPageNodeEditForm = {
    $conditionalTabs: {},
    $bioPageTab: {},
    $details: {},
    $rabbitHoleForm: {},
    $xmlSitemapForm: {},
    $metatagsForm: {},
    rabbitHoleInitialValue: {},
    xmlSitemapFormInitialValue: {},

    attach: function (context, settings) {
      var self = this;

      this.initVars(context);
      // Show/hide the Featured tab based on whether the state of the publish checkbox.
      self.toggleElements(context);

      $('#edit-field-publish-bio-page-value', context).on('change', function () {
        self.toggleElements(context);
      });
    },

    initVars: function (context) {
      this.$conditionalTabs = $('.horizontal-tab-button', context)
        .find('a[href$="bio-page-tab"]')
        .closest('li');
      this.$bioPageTab = $('#bio-page-tab', context);
      this.$details = $('details#bio-page-tab', context);
      this.$rabbitHoleForm = $('#edit-rabbit-hole', context);
      this.$xmlSitemapForm = $('#edit-xmlsitemap', context);
      this.$metatagsForm = $('#edit-field-metatags-0', context);

      // Hide the Rabbit Hole settings form.
      this.$rabbitHoleForm.addClass('js-hide');

      this.rabbitHoleInitialValue = 'bundle_default';
      this.xmlSitemapFormInitialValue = 'default';
    },

    toggleElements: function (context) {
      // Toggle display of form tab.
      this.showFormTab(context);
    },

    showFormTab: function (context) {
      // Show the form tab.
      if ($('#edit-field-publish-bio-page-value', context).is(':checked')) {
        this.showElements(context);
      }
      // Hide the form tab.
      else {
        this.hideElements();
      }
    },

    hideElements: function () {
      this.$conditionalTabs.addClass('js-hide');
      this.$xmlSitemapForm.addClass('js-hide');
      this.$metatagsForm.addClass('js-hide');
      this.resetValues();
    },

    showElements: function (context) {
      this.$conditionalTabs.removeClass('js-hide');
      this.$xmlSitemapForm.removeClass('js-hide');
      this.$metatagsForm.removeClass('js-hide');
      this.updateValues(context);
    },

    updateValues: function (context) {
      // Update XML sitemap inclusion status.
      this.updateXmlSitemapStatus(context);
      // Update Rabbit Hole settings.
      this.updateRabbitHoleSettings(context);
      $('#edit-field-metatags-0-advanced-robots-noindex').prop('checked', false);
    },

    resetValues: function () {
      this.$rabbitHoleForm
        .find('input[name="rh_action"][value="' + this.rabbitHoleInitialValue + '"]')
        .prop('checked', true);

      this.$xmlSitemapForm.val(this.xmlSitemapFormInitialValue);
      this.$xmlSitemapForm.find('option').removeAttr('selected');
      this.$xmlSitemapForm
        .find('option[value="' + this.xmlSitemapFormInitialValue + '"]')
        .attr('selected', 'selected');

      $('#edit-field-metatags-0-advanced-robots-noindex').prop('checked', true);
    },

    updateXmlSitemapStatus: function (context) {
      // Set the inclusion value to "Included".
      this.$xmlSitemapForm.val(1);
      this.$xmlSitemapForm.find('option').removeAttr('selected');
      this.$xmlSitemapForm.find('option[value="1"]').attr('selected', 'selected');
    },

    updateRabbitHoleSettings: function (context) {
      // Update Rabbit Hole to display page.
      $('#edit-rh-action-display-page').prop('checked', true);
    }
  };

})(jQuery, Drupal);
