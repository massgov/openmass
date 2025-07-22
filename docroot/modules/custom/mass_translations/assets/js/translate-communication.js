/**
 * Translate Communication Script
 *
 * This script detects language changes on mass.gov using Google Translate
 * and sends messages to embedded iframes (forms.mass.gov) to synchronize translations.
 */

(function ($, Drupal) {
  'use strict';

  window.addEventListener('message', function(event) {
    // Verify origin - adjust as needed

    // if (event.origin !== 'https://forms.mass.gov') return;

    if (event.data.action === 'getCookie') {
      notifyIframesOfLanguageChange('auto', getCurrentLanguage () );
    }
  });

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

  function getCurrentLanguage () {
    // alert(getLangFromFragment());
    const keyValue = document.cookie.match('(^|;) ?googtrans=([^;]*)(;|$)');
    return keyValue ? keyValue[2].split('/')[2] : null;

  }

  Drupal.behaviors.translateCommunication = {
    attach: function (context, settings) {
      // Store the current language to detect changes
      let currentLanguage = document.documentElement.lang || 'en';

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
