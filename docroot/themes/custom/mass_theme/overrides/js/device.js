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
          // Smart focus management that preserves accessibility while blocking problematic auto-focus
          let lastUserFocusTime = 0;
          let isProcessingUserFocus = false;
          let focusBlockCount = 0;
          let legitimateFocusCount = 0;

          // Monitor user-initiated focus events on hamburger menu
          document.addEventListener('focusin', function (event) {
            if (event.target.classList && event.target.classList.contains('js-header-menu-button')) {
              lastUserFocusTime = Date.now();
              isProcessingUserFocus = true;
              // Reset flag after a short delay
              setTimeout(function () {
                isProcessingUserFocus = false;
              }, 150);
              
              console.log('✅ User-initiated focus on hamburger menu detected');
            }
          }, true);

          // Override focus to intelligently block problematic auto-focus
          const originalFocus = HTMLElement.prototype.focus;
          HTMLElement.prototype.focus = function (options) {
            if (this && this.classList && this.classList.contains('js-header-menu-button')) {
              const timeSinceUserFocus = Date.now() - lastUserFocusTime;
              
              // Determine if this is legitimate accessibility focus or problematic auto-focus
              const isLegitimateFocus = (
                // Recent user interaction (within 200ms)
                timeSinceUserFocus < 200 ||
                // Currently processing user focus
                isProcessingUserFocus ||
                // Focus from keyboard navigation (tab/shift+tab)
                (options && options.focusVisible) ||
                // Focus from programmatic accessibility calls
                this.hasAttribute('data-accessibility-focus') ||
                // Focus from escape key or menu close
                this.hasAttribute('data-menu-close-focus')
              );

              if (isLegitimateFocus) {
                legitimateFocusCount++;
                console.log(`✅ Allowed legitimate accessibility focus #${legitimateFocusCount} on hamburger menu`);
                return originalFocus.call(this, options);
              } else {
                focusBlockCount++;
                console.log(`🚫 Blocked problematic auto-focus #${focusBlockCount} on hamburger menu (time since user focus: ${timeSinceUserFocus}ms)`);
                return; // Block the problematic auto-focus
              }
            }

            // Preserve original focus behavior for all other elements
            return originalFocus.call(this, options);
          };

          // Add debugging info to window object for testing
          window.hamburgerFocusDebug = {
            getStats: function () {
              return {
                blockedFocus: focusBlockCount,
                allowedFocus: legitimateFocusCount,
                lastUserFocus: lastUserFocusTime,
                isProcessingUserFocus: isProcessingUserFocus,
                timestamp: new Date().toISOString()
              };
            },
            resetStats: function () {
              focusBlockCount = 0;
              legitimateFocusCount = 0;
              lastUserFocusTime = 0;
              isProcessingUserFocus = false;
            }
          };

          console.log('🔧 Smart hamburger focus management activated for mobile devices requesting desktop site');
        }
      });
    }
  };
})(Drupal, once);
