/**
 * @file
 * Adds a localStorage item that provides a unique identifier for the user's device while on the site.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.massgovDevice = {
    attach: function (context) {
      once('massgovDeviceId', 'html', context).forEach(function () {
        if (typeof localStorage !== 'undefined') {
          if (window.crypto && localStorage.getItem('massgovDeviceId') === null) {
            if ('randomUUID' in window.crypto) {
              localStorage.setItem('massgovDeviceId', window.crypto.randomUUID());
            }
          }
        }
      });
    }
  };
})(Drupal, once);
