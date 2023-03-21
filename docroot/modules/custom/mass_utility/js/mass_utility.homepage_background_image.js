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
      '      <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 20 27"><path d="M94.5883 947.505C92.59 947.505 90.96430000000001 945.947 90.96430000000001 944.031C90.96430000000001 942.116 92.59 940.5569999999999 94.5883 940.5569999999999C96.5866 940.5569999999999 98.2121 942.1159999999999 98.2121 944.031C98.2121 945.947 96.5866 947.505 94.5883 947.505ZM94.5883 941.947C93.3893 941.947 92.41380000000001 942.882 92.41380000000001 944.031C92.41380000000001 945.1809999999999 93.3893 946.116 94.5883 946.116C95.7872 946.116 96.7626 945.181 96.7626 944.031C96.7626 942.882 95.78720000000001 941.947 94.5883 941.947ZM94.5027 961L86.8883 950.459C84.05030000000001 946.819 84.4654 940.846 87.7798 937.669C89.5756 935.947 91.9634 934.999 94.5029 934.999C97.0427 934.999 99.42999999999999 935.947 101.226 937.669C104.53999999999999 940.846 104.955 946.819 102.118 950.459ZM94.5029 936.389C92.3505 936.389 90.32679999999999 937.192 88.8046 938.651C86.0107 941.3299999999999 85.654 946.5649999999999 88.0564 949.636L88.0724 949.6569999999999L94.5027 958.559L100.95 949.636C103.352 946.5649999999999 102.995 941.3299999999999 100.20100000000001 938.651C98.67890000000001 937.192 96.65530000000001 936.389 94.50290000000001 936.389Z" fill-opacity="1" transform="matrix(1,0,0,1,-85,-934)"></path></svg>' +
      '    </dt>' +
      '    <dd class="ma__banner-credit__image-name">' + image.image_location + '</dd>' +
      '    <dd class="ma__banner-credit__image-author">' + image.image_credit + '</dd>' +
      '  </dl>' +
      '</div>'
    );
  });
})(jQuery, drupalSettings);
