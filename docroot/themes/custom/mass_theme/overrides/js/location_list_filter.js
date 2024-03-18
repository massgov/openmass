(function ($, Drupal) {
  ('use strict');

  console.log('apple');

  // Add id to location listing filter combobox options to pair with
  // its associated input field .ma__input pac-target-input.
  // '.pac-container' is an element by Google code.
  setTimeout(function () {
    $('.pac-container').attr('id', 'filter-loction-options');
  }, 250);

  // Update option box status.
  $('#filter-by-location').change(function () {
    console.log('open');

    if ($('#filter-loction-options').children().length > 0) {
      $(this).attr('aria-expanded', 'true');
    }

    console.log($(this).attr('aria-expanded'));
  });

  $('#filter-by-location').focusout(function () {
    console.log('close');

    if ($('#filter-loction-options').children().length === 0) {
      $(this).attr('aria-expanded', 'false');

      console.log($(this).attr('aria-expanded'));
    }
  });

})(jQuery, Drupal);
