/**
 * @file
 * Defines the callback invoked after google maps api library loads.
 *
 * @see mass_theme.libraries.yml > mass-google-map-apis-location library.
 * @see https://developers.google.com/maps/documentation/javascript/tutorial#Loading_the_Maps_API
 */

/**
 * Set a flag and emit an event that google maps library has loaded.
 */
(function (window, $) {
  'use strict';
  window.initGoogleMaps = function () {
    // Set a flag that the library has loaded, in case google maps js misses event.
    window.googleMapsLoaded = true;
    // Emit an event that the library has loaded.
    $(document).trigger('ma:LibrariesLoaded:GoogleMaps');
  };

})(window, jQuery);
