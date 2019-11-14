/**
 * @file
 * Implements a simple string search for content types on the node/add page.
 */

(function ($) {

  'use strict';

  Drupal.behaviors.ContentTypeSearch = {
    attach: function (context, settings) {

      var $parent = $('.admin-category-list');
      var $categoryListItem = $('.admin-category-list > li');
      var $contentTypeListItem = $('.admin-list li');

      $parent.append('<li class="no-results-msg" >No results found</li>');
      $('.no-results-msg').hide();

      $('#content-type-search').keyup(function (searchEvent) {
        $('.no-results-msg').hide();

        // Retrieve the input field
        var filter = $(searchEvent.currentTarget).val();

        // Find non matches and fade out
        var filtered = $contentTypeListItem.filter(function () {
          return ($(this).text().search(new RegExp(filter, 'i')) < 0);
        }).addClass('hidden');

        // Show all that were not just faded out.
        $contentTypeListItem.not(filtered).removeClass('hidden');

        // Hide categories where all items are hidden.
        var numTotal = 0;
        $categoryListItem.each(function () {
          var numShown = 0;
          var $children = $(this).find('.admin-list li');
          $children.each(function () {
            if (!$(this).hasClass('hidden')) {
              numShown++;
              numTotal++;
            }
          });
          if (numShown === 0) {
            $(this).addClass('hidden');
          }
          else {
            $(this).removeClass('hidden');
          }
        });

        // Display no results message when all items are hidden.
        if (numTotal === 0) {
          $('.no-results-msg').show();
        }
      });
    }
  };

  Drupal.behaviors.smoothScroll = {
    attach: function (context, settings) {

      var $anchorLink = $('.category-jump-links a');
      var $anchor = $('.type-category');

      // Adds negative top margin/padding to prevent anchors from "headbutting" the browser.
      $anchor.css({'margin-top': '-144px', 'padding-top': '144px'});

      // Add smooth scrolling for category links down to anchors.
      $anchorLink.on('click', function (event) {

        // Make sure this.hash has a value before overriding default behavior.
        if (this.hash !== '') {
          // Prevent default anchor click behavior,
          event.preventDefault();

          // Store hash
          var hash = this.hash;
          $('html, body').animate({
            scrollTop: $(hash).offset().top
          }, 500, function () {

            // Add hash (#) to URL when done scrolling (default click behavior).
            window.location.hash = hash;
          });
        } // End if
      });
    }
  };

})(jQuery);
