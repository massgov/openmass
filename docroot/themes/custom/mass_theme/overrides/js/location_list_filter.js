(function ($, Drupal) {
  ('use strict');
  // Add id to location listing filter combobox options to pair with
  // its associated input field .ma__input pac-target-input.
  // '.pac-container' is an element by Google code.
  setTimeout(function () {
    $('.pac-container').attr('id', 'filter-loction-options');
  }, 250);
})(jQuery, Drupal);
