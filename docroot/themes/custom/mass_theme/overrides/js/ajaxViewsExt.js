/**
 * @file
 * Extends drupal view AJAX filtering functionality with accessible announcements.
 */
(function ($, Drupal) {
  'use strict';

  // @TODO: revisit and confirm this language.
  // See: https://jira.state.ma.us/browse/MASSGOV-1208
  var FILTERED_ANNOUNCEMENT = 'New content loaded. Now displaying a new set of filtered items.';

  Drupal.behaviors.ajaxViewsExt = {
    attach: function (context, settings) {
      // We hook off of the document-level view ajax event
      $(document).once('views-ajax').ajaxComplete(function (e, xhr, settings) {
        xhr.done(function () {
          Drupal.announce(
        Drupal.t(FILTERED_ANNOUNCEMENT)
      );
        });
      });
    }
  };
})(jQuery, Drupal);
