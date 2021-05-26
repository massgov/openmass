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
    filters.className = exp ? 'show' : 'hide';
    button.innerHTML = exp ? 'Hide Filters' : 'Show Filters';
    button.setAttribute('aria-expanded', expanded)
    button.className = exp ? 'expanded' : ''
  }
  toggleButton();
  button.onclick = () => {
    expanded = !expanded;
    toggleButton(expanded);
  };
  
})();
