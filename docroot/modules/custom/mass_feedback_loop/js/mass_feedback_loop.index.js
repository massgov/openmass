/**
 * @file
 * Mass Feedback Loop custom JS.
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.massFeedbackLoop = {
    attach: function attach(context, settings) {
      var $filterByPage = $('#edit-filter-by-page', context);

      // Updates 'tablesort' CSS classes based on current sorting values.
      $('th span[data-sort-by]', context).removeClass(function (index, className) {
        // Removes any 'tablesort' classes to reset sorting.
        return (className.match(/(^|\s)tablesort tablesort--\S+/g) || []).join(' ');
      });
      var sortByVariant = parseInt($('#edit-sort-by', context).val());
      var order = 'desc';
      var $colToSortBy = $('th span[data-sort-by]', context).filter(function () {
        if (drupalSettings.massFeedbackLoop.sortingVariants[sortByVariant]) {
          order = (drupalSettings.massFeedbackLoop.sortingVariants[sortByVariant].desc) ? 'desc' : 'asc';
          return $(this).data('sortBy') === drupalSettings.massFeedbackLoop.sortingVariants[sortByVariant].order_by;
        }
      });
      $colToSortBy.addClass('tablesort tablesort--' + order);

      // Removes unnecessary query parameters from pager URLs.
      // Acts as workaround for current known Drupal 8 core issue:
      // @see https://www.drupal.org/project/drupal/issues/2504709
      var origin = window.location.origin;
      var pathname = window.location.pathname;
      var $elements = $(once('massFeedbackLoop', 'nav.pager a', context));
      $elements.each(function () {
        var url = new URL(origin + pathname + $(this).attr('href'));
        if (url.searchParams.has('ajax_form')) {
          url.searchParams.delete('ajax_form');
        }
        if (url.searchParams.has('_wrapper_format')) {
          url.searchParams.delete('_wrapper_format');
        }
        $(this).attr('href', url.search);
      });

      // Prevents form submission via 'Enter' key press.
      // Triggers rebuild of feedback table via event on <select> element.
      $filterByPage.on('keypress', function (e) {
        if (e.keyCode === 13) {
          e.preventDefault();
        }
      });

      // Open / close functionality for the survey.
      $('#feedback-table', context).on('click', '.survey-toggle', function () {
        var $parentRow = $(this).closest('tr');
        var $targetRow = $parentRow.next();

        // Toggle parent classes
        $parentRow.toggleClass('expanded');
        $targetRow.toggleClass('open');

      });

      // Open / close survey text cells
      var $feedbackTextCell = $('#feedback-table .survey-text', context);
      var feedbackCount = 92;
      var medium = 1000;
      var long = 2000;
      var veryLong = 3000;

      $feedbackTextCell.each(function () {
        var $cell = $(this);
        var charCount = $cell.text().length;
        var $td = $cell.closest('.survey-response');

        if (charCount >= feedbackCount) {
          if (charCount >= medium && charCount < long) {
            $td.addClass('text-medium');
          }
          else if (charCount >= long && charCount < veryLong) {
            $td.addClass('text-long');
          }
          else if (charCount >= veryLong) {
            $td.addClass('text-very-long');
          }
        }

      });

    }
  };
})(jQuery, Drupal);
