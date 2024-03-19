// (function ($, Drupal) {
//   ('use strict');
//   // Add id to location listing filter combobox options to pair with
//   // its associated input field .ma__input pac-target-input.
//   // '.pac-container' is an element by Google code.
//   setTimeout(function () {
//     // eslint-disable-next-line quotes
//     $('.pac-container:nth-child(2)').attr({
//       id: 'filter-loction-options',
//       role: 'listbox',
//       aria-label: ''
//     });
//   }, 250);

//   // Update option box status.
//   $('#filter-by-location').keyup(function () {
//     if ($('#filter-loction-options').children().length > 0) {
//       $(this).attr('aria-expanded', 'true');
//     }
//     else {
//       $(this).attr('aria-expanded', 'false');
//     }

//     console.log($(this).attr('aria-expanded'));
//   });

//   // $('.pac-container').each(function (i) {
//   //   i.on('click', function() {
//   //   console.log('clicked');

//   //    $('#filter-by-location').attr('aria-expanded', 'false');
//   //   $(this).css('border', '3px solid red');
//   // });

//   $('.pac-item').each(function (i) {
//     i.on('click', function () {
//       $(this).css('background', 'orange');
//       console.log('selected');
//       $('#filter-by-location').attr('aria-expanded', 'false');
//     });
//   });


// })(jQuery, Drupal);
