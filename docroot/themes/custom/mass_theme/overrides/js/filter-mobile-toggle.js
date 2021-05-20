/**
 * @file
 * Support views with filters 
 */

/* views-view--data-listing */

(function () {
  'use strict';
  // set initial filter state to collapsed
  let expanded = false;
  const button = document.getElementById('filter-toggle');
  const filters = document.getElementById('filters')
  const toggleButton = (exp) => {
    filters.style.display = exp ? 'block' : 'none';
    button.innerHTML = exp ? 'Hide Filters' : 'Show Filters';
    button.setAttribute('aria-expanded', expanded)
  }
  toggleButton();
  button.onclick = () => {
    expanded = !expanded;
    toggleButton(expanded);
  };
  
})();
