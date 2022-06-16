/**
 * @file
 * Support views with filters
 */

/* views-view--data-listing */

(function () {
  'use strict';
  // set initial filter state to collapsed
  var expanded = false;
  var button = document.getElementById('filter-toggle');
  var filters = document.getElementById('filters');
  function toggleButton(exp) {
    filters.className = exp ? 'show' : 'hide';
    button.innerHTML = exp ? 'Hide Filters' : 'Show Filters';
    button.setAttribute('aria-expanded', expanded);
    button.className = exp ? 'expanded' : '';
  }
  button.onclick = function () {
    expanded = !expanded;
    toggleButton(expanded);
  };

})();
