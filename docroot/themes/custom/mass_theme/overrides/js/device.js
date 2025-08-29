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

        // Fix Android keyboard dismissal caused by Mayflower hamburger menu focus
        if (/Android/i.test(navigator.userAgent)) {
          const originalFocus = HTMLElement.prototype.focus;
          HTMLElement.prototype.focus = function(options) {
            if (this.classList && this.classList.contains('js-header-menu-button')) {
              return; // Block hamburger menu focus on Android
            }
            return originalFocus.call(this, options);
          };
        }
      });
    }
  };
})(Drupal, once);
