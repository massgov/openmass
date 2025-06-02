(function (Drupal) {
  'use strict';

  Drupal.behaviors.customIframeMessageCallback = {
    attach: function (context, settings) {
      // Only once per iframe
      once('once-iframe-message-callback', '.js-ma-responsive-iframe', context).forEach(function (element) {
        const iframe = this;

        // Wait until the iframeResizer instance is attached
        if (iframe.iFrameResizer) {
          const originalCallback = iframe.iFrameResizer.options.messageCallback;

          // Create a new callback that wraps the original
          iframe.iFrameResizer.options.messageCallback = function (messageData) {
            const {message} = messageData;

            if (message.type === 'scrollToFocus') {
              const iframeTop = iframe.getBoundingClientRect().top + window.scrollY;
              const scrollTarget = iframeTop + message.offset;
              const adjustment = -250;

              window.scrollTo({
                top: scrollTarget + adjustment,
                behavior: 'smooth'
              });
            }

            // Call the original callback if it exists
            if (typeof originalCallback === 'function') {
              originalCallback(messageData);
            }
          };
        }
        else {
          // Optional: retry if iframeResizer hasn't initialized yet
          console.warn('iframeResizer not yet available on this iframe:', iframe);
        }
      });
    }
  };
})(Drupal);
