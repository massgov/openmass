/**
 * @file
 * Support accessibility features for site search.
 */

/* autosuggest optionlist status update */

(function () {
  'use strict';

  var searchInputs = document.querySelectorAll('.ma__header-search__input');

  // There are 2 sets of search components per page, one on the page for desktop version,
  // the other on the flyout of the hamburger menu for mobile version though only one set is visible at a time with css.
  // Find the one user is on.
  searchInputs.forEach((input) => {

    input.addEventListener("keyup", (e) => {
      let activeInput = e.target;
      let suggestionContainer = e.target.nextElementSibling;

      if (suggestionContainer.classList.contains('ma__suggestions')) {
        // Adjust the timing that suggestions get inserted.
        setTimeout(() => {
          // div#suggestions-list remains after its child elements are removed.
          // Check it has child elements or not.
          let suggestionList = suggestionContainer.querySelector("#suggestions-list").hasChildNodes();

          if (suggestionList) {
            activeInput.setAttribute("aria-expanded", true);
          }

          if (!suggestionList) {
            activeInput.setAttribute("aria-expanded", false);
          }
        }, 900);
      }
    });
  });
})();
