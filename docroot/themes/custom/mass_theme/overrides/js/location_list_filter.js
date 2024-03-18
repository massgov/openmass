(function ($, Drupal) {
  ('use strict');
  // Add id to location listing filter combobox options to pair with
  // its associated input field .ma__input pac-target-input.
  // '.pac-container' is an element by Google code.
  setTimeout(function () {
    $('.pac-container').attr('id', 'filter-loction-options');
  }, 250);

  // Update option box status.
  // $('#filter-by-location').change(function () {
  //   if ($('#filter-loction-options').children().length > 0) {
  //     $(this).attr('aria-expanded', 'true');
  //   }
  // });

  $('#filter-loction-options').childrenchildren().change(function () {
    var locationOptionField = $('#filter-by-location');
    if ($(this).length > 0) {
      locationOptionField.attr('aria-expanded', 'true');
    } else {
      locationOptionField.attr('aria-expanded', 'false');
    }
  });
})(jQuery, Drupal);
