/**
 * @file
 * Extends Drupal object with background images so we can show random images when a user refreshed the page.
 */
/* eslint quotes: ["error", "single", { "avoidEscape": true }]*/

(function ($, drupalSettings) {
  'use strict';

  /**
   * Rewrite the style block for the homepage search banner to show a randomized image.
   */
  $('#GUID935283478').each(function () {
    // @see https://stackoverflow.com/questions/4550505/getting-a-random-value-from-a-javascript-array
    var images = drupalSettings['homepage_background_images'];
    var image = images[Math.floor(Math.random() * images.length)];
    $('style', this).html(
      "#GUID935283478 {background-image: url('" + image.image_url_mobile + "');}" +
      '@media (min-width: 801px) {' +
      "  #GUID935283478 { background-image: url('" + image.image_url + "'); }" +
      '}'
    );
    $(this).append(
      '<div class="ma__banner-credit" aria-hidden="true">' +
      '  <dl class="ma__banner-credit__container">' +
      '    <dt class="ma__banner-credit__icon">' +
      '      <span class="ma__visually-hidden">Credit for the banner image</span>' +
      '      <svg viewBox="0 0 24 24" height="24" width="24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" fill="currentColor"><path d="M12 6a3.75 3.75 0 1 0 0 7.5A3.75 3.75 0 0 0 12 6m0 6a2.25 2.25 0 1 1 0-4.5 2.25 2.25 0 0 1 0 4.5m0-10.5a8.26 8.26 0 0 0-8.25 8.25c0 2.944 1.36 6.064 3.938 9.023a23.8 23.8 0 0 0 3.885 3.591.75.75 0 0 0 .861 0 23.8 23.8 0 0 0 3.879-3.59c2.573-2.96 3.937-6.08 3.937-9.024A8.26 8.26 0 0 0 12 1.5m0 19.313c-1.55-1.22-6.75-5.696-6.75-11.063a6.75 6.75 0 0 1 13.5 0c0 5.365-5.2 9.844-6.75 11.063"/></svg>' +
      '    </dt>' +
      '    <dd class="ma__banner-credit__image-name">' + image.image_location + '</dd>' +
      '    <dd class="ma__banner-credit__image-author">' + image.image_credit + '</dd>' +
      '  </dl>' +
      '</div>'
    );
  });
})(jQuery, drupalSettings);
