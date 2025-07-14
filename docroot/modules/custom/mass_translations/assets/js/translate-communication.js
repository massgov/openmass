/**
 * Translate Communication Script
 *
 * This script detects language changes on mass.gov using Google Translate
 * and sends messages to embedded iframes (forms.mass.gov) to synchronize translations.
 */

(function ($, Drupal) {
  'use strict';
  // Translate on page load if previously selected.
  // Not sure iw we need this.
  window.addEventListener('message', function(event) {
    // Verify origin - adjust as needed
    // alert(event.origin);
    // if (event.origin !== 'https://forms.mass.gov') return;

    if (event.data.type === 'getCookie') {
      // Send cookie value back to iframe
      event.source.postMessage({
        action: 'cookieResponse',
        lang: get_current_lang(),
      }, '*');
    }
  });

  function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? match[2] : null;
  }

  Drupal.behaviors.translateCommunication = {
    attach: function (context, settings) {
      // Store the current language to detect changes
      let currentLanguage = document.documentElement.lang || 'en';

      /**
       * Function to notify all iframes about language changes
       * @param {string} from - Source language code
       * @param {string} to - Target language code
       */
      function notifyIframesOfLanguageChange(from, to) {
        // Find all iframes on the page
        const iframes = document.querySelectorAll('iframe.js-iframe-resizer');

        // Send postMessage to each iframe
        iframes.forEach(iframe => {
          try {
            // Only send to iframes that are loaded and have a contentWindow
            if (iframe.contentWindow) {
              const message = {
                action: 'translate',
                language: to
              };

              // Send the message to the iframe
              iframe.contentWindow.postMessage(message, '*');
            }
          }
          catch (error) {
            console.error('Error sending message to iframe:', error);
          }
        });
      }

      /**
       * Method 1: Detect language changes by observing HTML lang attribute changes
       */
      const htmlObserver = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
          if (mutation.type === 'attributes' && mutation.attributeName === 'lang') {
            const newLanguage = document.documentElement.lang;
            if (newLanguage && newLanguage !== currentLanguage) {
              console.log(`Language changed from ${currentLanguage} to ${newLanguage}`);
              notifyIframesOfLanguageChange(currentLanguage, newLanguage);
              currentLanguage = newLanguage;
            }
          }
        });
      });

      // Start observing the HTML element for lang attribute changes
      htmlObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['lang'] });
    }
  };
})(jQuery, Drupal);
