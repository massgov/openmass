(function ($, Drupal) {
  ('use strict');
  // Add id to location listing filter combobox options to pair with
  // its associated input field .ma__input pac-target-input.
  // '.pac-container' is an element by Google code.
  setTimeout(function () {
    $('.pac-container').attr('id', 'filter-loction-options');
  }, 250);

  // Update option box status.
  $('#filter-by-location').keyup(function () {
    if ($('#filter-loction-options').children().length > 0) {
      $(this).attr('aria-expanded', 'true');
    }
    else {
      $(this).attr('aria-expanded', 'false');
    }

    console.log($(this).attr('aria-expanded'));
  });

  // $('.pac-item').on('click', function () {
  //   console.log('selected');

  //   // if ($('#filter-loction-options').children().length === 0) {


  //   console.log($(this).attr('aria-expanded'));
  //   // }
  // });

  $('.pac-item').each(function (i) {
    i.on('click', function () {
      $(this).css('background', 'orange');
      console.log('selected');
      $('#filter-by-location').attr('aria-expanded', 'false');
    });
  });


})(jQuery, Drupal);
