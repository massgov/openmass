/**
 * Translate Communication Script
 *
 * This script detects language changes on mass.gov using Google Translate
 * and sends messages to embedded iframes (forms.mass.gov) to synchronize translations.
 */

(function ($, Drupal, once) {
  'use strict';

  /**
   * Listen for gform_post_render hook, specifically to translate the form after the errors.
   *
   */
  window.addEventListener('message', function (event) {
    const iframes = document.querySelectorAll('iframe.js-iframe-resizer');

    // Send postMessage to each iframe
    iframes.forEach(iframe => {
      const srcUrl = iframe.getAttribute('src');
      const urlObject = new URL(srcUrl);
      // Extract the domain (protocol + hostname)
      const domain = urlObject.origin;
      // Ensure the message is from the trusted origin.
      if (event.origin === domain) {
        if (event.data.action === 'gform_post_render') {
          notifyIframesOfLanguageChange('auto', getCurrentLanguage());
        }
      }
    });


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
        var srcUrl = iframe.getAttribute('src');
        var urlObject = new URL(srcUrl);
        // Extract the domain (protocol + hostname)
        var domain = urlObject.origin;
        // Only send to iframes that are loaded and have a contentWindow
        if (iframe.contentWindow) {
          const message = {
            action: 'translate',
            language: to
          };

          // Send the message to the iframe
          iframe.contentWindow.postMessage(message, domain);
        }
      }
      catch (error) {
        console.error('Error sending message to iframe:', error);
      }
    });
  }

  /**
   * Retrieves the current language from the browser's cookies, if available.
   *
   * @return {string|null} The code of the current language (e.g., "en", "fr"), or null if not set.
   */
  function getCurrentLanguage() {
    // alert(getLangFromFragment());
    const keyValue = document.cookie.match('(^|;) ?googtrans=([^;]*)(;|$)');
    return keyValue ? keyValue[2].split('/')[2] : null;

  }


  /**
   * Drupal behavior for managing the Translate Communication functionality.
   *
   * This behavior is triggered when the page is ready or content is loaded via
   * AJAX. It handles the initialization and functionality related to translation
   * communication within a Drupal site.
   *
   * @namespace
   * @property {Object} attach - Function that initializes or applies the behavior
   *                             to a specific context. It is triggered whenever
   *                             new content is added to the page.
   * @param {Object} context - The context of the current DOM to apply the behavior.
   *                           Typically provided by Drupal, it represents the area
   *                           where the behavior needs to be activated. If no specific
   *                           context is provided, the behavior is applied to the entire page.
   * @param {Object} settings - An object containing Drupal's dynamic settings that may be
   *                            used to further customize or configure the behavior.
   */
  Drupal.behaviors.translateCommunication = {
    attach: function (context, settings) {
      // Use once to ensure this only runs once per page load
      once('translate-communication', 'html', context).forEach(function (element) {
        // Store the current language to detect changes
        let currentLanguage = document.documentElement.lang || 'en';

        /**
         * Detect language changes by observing HTML lang attribute changes.
         */
        const htmlObserver = new MutationObserver(mutations => {
          mutations.forEach(mutation => {
            if (mutation.type === 'attributes' && mutation.attributeName === 'lang') {
              const newLanguage = document.documentElement.lang;
              if (newLanguage && newLanguage !== currentLanguage) {
                notifyIframesOfLanguageChange(currentLanguage, newLanguage);
                currentLanguage = newLanguage;
              }
            }
          });
        });

        // Start observing the HTML element for lang attribute changes
        htmlObserver.observe(document.documentElement, {attributes: true, attributeFilter: ['lang']});
      });
    }
  };
})(jQuery, Drupal, once);
