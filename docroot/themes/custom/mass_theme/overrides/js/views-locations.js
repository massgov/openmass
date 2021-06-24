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
        if (settings.url == '/views/ajax?_wrapper_format=drupal_ajax') {
          var DOMContentLoaded_event = document.createEvent("Event")
          DOMContentLoaded_event.initEvent("DOMContentLoaded", true, true)
          window.document.dispatchEvent(DOMContentLoaded_event)
        }
      });
    }
  };
})(jQuery, Drupal);
