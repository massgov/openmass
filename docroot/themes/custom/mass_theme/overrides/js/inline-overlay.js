(function ($) {
  'use strict';

  var tocFocusableElements = $('.js-inline-overlay').find(':focusable');
  var firstFocusableElement = tocFocusableElements[0];
  var lastFocusableElement = tocFocusableElements[tocFocusableElements.length - 1];
  var tocButtons = $('.ma__toc--hierarchy__container .js-accordion-link');
  var tocLastButton = tocButtons[tocButtons.length - 1];

  $(tocLastButton).keydown(function (e) {
    var key = (e.keyCode ? e.keyCode : e.witch);
    var lastContainer = $(tocLastButton).closest('.ma__toc--hierarchy__accordion');

    if (!lastContainer.hasClass('is-open')) {
      // When tab key is hit on the last ficusable element,
      if (key === '9') {
        // Set focus on the first focusable element in the overlay.
        firstFocusableElement.focus();
      }
    }

    // When the sub menu is open.
    if (lastContainer.hasClass('is-open')) {
      $(lastFocusableElement).keydown(function (e) {
        var key = (e.keyCode ? e.keyCode : e.witch);
        // When tab key is hit on the last ficusable element,
        if (key === '9') {
          // Set focus on the first focusable element in the overlay.
          firstFocusableElement.focus();
        }
      });
    }
  });
})(jQuery);
