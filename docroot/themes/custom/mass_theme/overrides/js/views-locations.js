/**
 * @file
 *
 */
(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.viewsLocations = {
    attach: function (context, settings) {
      // We hook off of the document-level view ajax event
      $(document).once('views-locations').ajaxComplete(function (e, xhr, settings) {
        if (settings.url === '/views/ajax?_wrapper_format=drupal_ajax') {
          var DOMContentLoaded_event = document.createEvent('Event');
          DOMContentLoaded_event.initEvent('DOMContentLoaded', true, true);
          window.document.dispatchEvent(DOMContentLoaded_event);
        }
      }).on('ma:GoogleMaps:placeChanged', function (event, place) {

        $('.views-exposed-form input[type="text"]').val('');

        if (place.address_components) {
          for (var i = 0; i < place.address_components.length; i++) {

            var component = place.address_components[i];
            if (component.types[0] === 'route') {
              $('.views-exposed-form [name="address"]').val(component.long_name);
            }

            if (component.types[0] === 'locality') {
              $('.views-exposed-form [name="city"]').val(component.long_name);
            }

            if (component.types[0] === 'postal_code') {
              $('.views-exposed-form [name="zipcode"]').val(component.long_name);
            }
          }
        }

      });

      $('form.js-location-filters', context).on('submit', function (e) {
        e.preventDefault();
        var $this = $(this);
        var location = $this.find('#filter-by-location').val();
        var wheelchair = $this.find('#wheelchair').is(':checked');

        if (!location) {
          $('.views-exposed-form input[type="text"]').val('');
        }
        else {
          $('.views-exposed-form [name="helper"]').val(location);
        }

        if (wheelchair) {
          var option = $('.views-exposed-form [name="icons"] option')[1];
          $('.views-exposed-form [name="icons"]').val(option.value);
        }
        else {
          $('.views-exposed-form [name="icons"]').val('All');
        }

        $('.views-exposed-form .form-submit').trigger('click');
      });


      $('.js-results-heading-tag', context).on('click', function (e) {
        e.preventDefault();
        var $form = $('form.js-location-filters');

        if ($(this).data('ma-filter-type') === 'location') {
          $form.find('input').val('');
        }
        else {
          $form.find('input').prop('checked', false);
        }

        $form.trigger('submit');
      });

      $('.js-results-heading-clear', context).on('click', function (e) {
        e.preventDefault();
        var $form = $('form.js-location-filters');
        $form.find('input').val('').prop('checked', false);
        $form.trigger('submit');
      });

    }
  };
})(jQuery, Drupal);
