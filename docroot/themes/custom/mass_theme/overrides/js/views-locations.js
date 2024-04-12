/**
 * @file
 *
 */
(function ($, Drupal) {
  ('use strict');

  Drupal.behaviors.viewsLocations = {
    attach: function (context, settings) {
      // We hook off of the document-level view ajax event
      $(document)
        .ajaxComplete(function (e, xhr, settings) {
          if (settings.url === '/views/ajax?_wrapper_format=drupal_ajax') {
            var DOMContentLoaded_event = document.createEvent('Event');
            DOMContentLoaded_event.initEvent('DOMContentLoaded', true, true);
            window.document.dispatchEvent(DOMContentLoaded_event);
          }
        })
        .on('ma:GoogleMaps:placeChanged', function (event, place) {
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
  var $filterButton = $('.js-location-filters__submit');
  $(document).ready(function () {
    var referrer = document.referrer.substr(
      document.referrer.lastIndexOf('?') + 1
    );
    referrer = '?' + referrer;
    var urlParams = new URLSearchParams(window.location.search);
    var $displayedResultRange = $('#displayedResultRange');
    if (urlParams.size > 0 && !$('#error-input').hasClass('has-error')) {
      if (urlParams.has('icons')) {
        // with filter options
        if (urlParams.has('page')) {
          setFocusForVO('#displayedResultRange', $displayedResultRange);
          $displayedResultRange.focus();
        }
        else {
          setFocusForVO('#filterButton', $filterButton);
          $filterButton.focus();
        }
      }
      else {
        // no filter options
        if (urlParams.has('page')) {
          setFocusForVO('#displayedResultRange', $displayedResultRange);
          $displayedResultRange.focus();
        }
      }

      // Tell sr users the new listing is rendered.
      if (urlParams !== referrer) {
        $filterButton.attr('aria-describedby', 'sr-note-refresh');
      }
    }
  });

  $filterButton.on('focusout', function (e) {
    if ($(this).attr('aria-describedby')) {
      $(this).removeAttr('aria-describedby');
    }
  });

  // Set focus on the input field when the error message is displayed.
  $filterButton.on('click', function (e) {
    errorMessageHandling();
  });
  // Adjustment for VoiceOver.
  $filterButton.on('keydown', function (e) {
    if (e.ctrlKey + e.altKey) {
      // VO keys
      // Click
      if (e.key === '') {
        errorMessageHandling();
      }
      // Move away from the button
      if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
        if ($(this).attr('aria-describedby')) {
          $(this).removeAttr('aria-describedby');
        }
      }
    }
    else {
      // When VO keys are already held down.
      // Click
      if (e.key === '') {
        errorMessageHandling();
      }
      // Move away from the button
      if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
        $filterButton.removeAttr('aria-describedby');
      }
    }
  });

  function errorMessageHandling() {
    var $locationField = $('#filter-by-location');
    setTimeout(function () {
      if ($('#error-input').hasClass('has-error')) {
        $locationField.attr('aria-invalid', 'true');
        if ($locationField.val() !== '') {
          // Need to be expressively 'empty'.
          $locationField.attr(
            'aria-describedby',
            'error-input sr-note-error'
          );
        }
        else {
          $locationField.attr('aria-describedby', 'error-input sr-note');
        }
        $locationField.focus();
      }
      else {
        $locationField.removeAttr('aria-invalid');
        $locationField.attr('aria-describedby', 'sr-note');
      }
    }, 100);
  }

  // Enfoce focus() with VO.
  function setFocusForVO(position, focusTarget) {
    var device = window.navigator.userAgent;
    if (device.indexOf('Macintosh') !== -1 ||
        device.indexOf('iPhone') !== -1 ||
        device.indexOf('iPad') !== -1) {

      setTimeout(function () {
        // window.location.hash = position;

        var focusInterval = 10; // ms, time between function calls
        var focusTotalRepetitions = 10; // number of repetitions

        focusTarget.blur();

        var focusRepetitions = 0;
        var interval = window.setInterval(function () {
          focusTarget.focus();

          if (focusTarget === '#filterButton') {
            // Tell sr users the new listing is rendered.
            $filterButton.attr('aria-describedby', 'sr-note-refresh');
          }

          focusRepetitions++;
          if (focusRepetitions >= focusTotalRepetitions) {
            window.clearInterval(interval);
          }
        }, focusInterval);
      }, 1100);
    }
  }
})(jQuery, Drupal);
