/**
 * @file
 *
 */
(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.viewsLocations = {
    attach: function (context, settings) {
      // We hook off of the document-level view ajax event
      $(document).ajaxComplete(function (e, xhr, settings) {
        if (settings.url === '/views/ajax?_wrapper_format=drupal_ajax') {
          var DOMContentLoaded_event = document.createEvent('Event');
          DOMContentLoaded_event.initEvent('DOMContentLoaded', true, true);
          window.document.dispatchEvent(DOMContentLoaded_event);
        }
      }).on('ma:GoogleMaps:placeChanged', function (event, place) {
        $('.views-exposed-form input[type="text"]').val('');
        if (place.geometry && place.geometry.location) {
          var lat = place.geometry.location.lat();
          var lng = place.geometry.location.lng();
          $('.views-exposed-form [name="lat"]').val(lat);
          $('.views-exposed-form [name="lng"]').val(lng);
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

  // Set focus on the button when the page is refreshed with the filter options.
  $(document).ready(function () {
    console.log($(location).attr('href'));
    if ($(location).attr('href').contains('?icons=')) {
      $('.js-location-filters__submit').focus();
    }
  });

  // Set focus on the input field when the error message is displayed.
  $('.js-location-filters__submit').on('click', function () {
    var locationField = $('#filter-by-location');
    if (!$(locationField).val()) {
      $(locationField).attr('aria-invalid', 'true');
      $(locationField).attr('aria-describedby', 'error-input sr-note');
      $(locationField).focus();
    }
    else {
      $(locationField).removeAttr('aria-invalid');
      $(locationField).attr('aria-describedby', 'sr-note');
    }
  });

})(jQuery, Drupal);
