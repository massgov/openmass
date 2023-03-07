/**
 * @file
 * Adds a localStorage item that provides a unique identifier for the user's device while on the site.
 */

(function ($) {
  'use strict';

  Drupal.behaviors.massgovDevice = {
    attach: function (context) {
      once('massgovDeviceId', 'html', context).forEach(function() {
        if (window.crypto && localStorage.getItem('massgovDeviceId') === null) {
          localStorage.setItem('massgovDeviceId', window.crypto.randomUUID());
        }
      });
    }
  };
})(jQuery);
