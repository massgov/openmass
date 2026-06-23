/**
 * @file
 * Adds a localStorage item that provides a unique identifier for the user's device while on the site.
 * Also fixes mobile keyboard dismissal caused by Mayflower hamburger menu auto-focus.
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

        // Fix mobile keyboard dismissal caused by Mayflower hamburger menu auto-focus
        // Only apply when mobile device is requesting desktop site
        function shouldApplyKeyboardFix() {
          // Quick user agent check first (most efficient)
          const mobileUA = /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent);
          if (mobileUA) {
            return false; // Mobile user agent = mobile site = no fix needed
          }

          // Desktop user agent detected - check if device is actually mobile
          const hasTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
          const smallScreen = window.screen.width <= 1024;

          return hasTouch || smallScreen; // Mobile device with desktop user agent
        }

        if (shouldApplyKeyboardFix()) {
          const originalFocus = HTMLElement.prototype.focus;
          HTMLElement.prototype.focus = function (options) {
            // Only block hamburger menu focus, preserve all other focus behavior
            if (this && this.classList && this.classList.contains('js-header-menu-button')) {
              // Hamburger menu focus blocked to prevent keyboard dismissal
              return;
            }
            // Preserve original focus behavior for all other elements
            return originalFocus.call(this, options);
          };
        }
      });
    }
  };
})(Drupal, once);
