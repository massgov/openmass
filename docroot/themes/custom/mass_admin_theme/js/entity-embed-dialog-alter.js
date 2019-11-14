/**
 * @file
 * Entity embed dialog alter
 *
 * This also supports collapsible navigable is the 'is-collapsible' class is
 * added to the main element, and a target element is included.
 */
(function ($, Drupal) {

  'use strict';

  /**
   * Initialise the imageStyle JS.
   */
  Drupal.behaviors.imageStyle = {
    attach: function (context, settings) {
      var image_style = $('.js-form-item-attributes-data-entity-embed-display-settings-image-style select');
      var align_fieldset = $('fieldset.fieldgroup[data-drupal-selector="edit-attributes-data-align"]');

      image_style.find('option').each(function (e) {
	var value = $(this).attr('value').trim();
	if (value !== 'embedded_full_width' && value !== 'embedded_half_width') {
	  image_style.find('option[value="' + value + '"]').remove();
	}
      });

      hide_show_align(image_style, align_fieldset);

      image_style.on('change', function () {
	hide_show_align($(this), align_fieldset);
      });
    }
  };

  /**
   * Helper function to hide and show the alignment fieldset.
   */
  function hide_show_align(trigger, target) {
    if (trigger.val() === 'embedded_full_width') {
      target.hide();
    }
    else if (trigger.val() === 'embedded_half_width') {
      target.show();
    }
  }

})(jQuery, Drupal);
