/**
 * @file
 * Adds Drupal announce functionality for location listing component.
 *
 */

(function ($, Drupal) {
  'use strict';

  // Announce number of shown results, total results, and filter/sort info when new content loads.
  $('.js-location-listing').each(function () {
    var $el = $(this);
    var $resultsHeading = $el.find('.js-results-heading');

    // See: mayflower artifacts > assets/js/modules/resultsHeading.js
    $resultsHeading.on('ma:ResultsHeading:DataUpdated', function (e, data) {
      var message = 'New content loaded.';
      var numResults = data.numResults.split(' - ').join(' through ');
      var filters = [];
      var sortMessage = '';
      var filterMessage = '';

      // If there are active filters, add that to the announcement.
      if (data.tags) {
        data.tags.forEach(function (tag) {
          if (tag.type === 'tag') {
            filters.push(tag.text);
          }
          if (tag.type === 'location') {
            sortMessage += ', sorted by proximity to ' + tag.text;
          }
        });

        if (filters.length) {
          filterMessage = ' that are ' + filters.join(' and ');
        }
      }

      message += '  Now showing ' + numResults + ' of ' + data.totalResults + ' locations' + filterMessage + sortMessage;

      Drupal.announce(message);
    });
  });
})(jQuery, Drupal);
