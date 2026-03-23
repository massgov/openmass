(function ($, Drupal) {
  'use strict';

  // Function to handle focus messages from iframes
  function handleFocusMessage(messageData) {
    const message = messageData.message || messageData;
    const iframe = messageData.iframe;

    if (!message || !message.type) {
      return;
    }

    if (!iframe) {
      console.warn('No iframe found in message data');
      return;
    }

    if (message.type === 'focusIframeForError') {
      if (!iframe.hasAttribute('tabindex')) {
        iframe.setAttribute('tabindex', '-1');
      }

      try {
        iframe.focus({ preventScroll: true });
      }
      catch (e) {
        iframe.focus();
      }

      return;
    }

    if (message.type === 'scrollToFocus' || message.type === 'scrollToValidationError') {
      const isValidationError = message.type === 'scrollToValidationError';
      const behavior = isValidationError ? 'auto' : 'smooth';

      // Check if the iframe is within a modal
      const modal = iframe.closest('.tingle-modal-box__content');

      if (modal) {
        // Handle focus within modal - scroll the modal content
        const modalRect = modal.getBoundingClientRect();
        const iframeRect = iframe.getBoundingClientRect();
        const relativeIframeTop = iframeRect.top - modalRect.top;
        const scrollTarget = modal.scrollTop + relativeIframeTop + (message.offset || 0);
        const adjustment = -250;
        const finalScrollPosition = Math.max(0, scrollTarget + adjustment);

        modal.scrollTo({
          top: finalScrollPosition,
          behavior: behavior
        });
      }
      else {
        // Handle focus in regular page iframe
        const iframeTop = iframe.getBoundingClientRect().top + window.scrollY;
        const scrollTarget = iframeTop + (message.offset || 0);
        const adjustment = -250;
        const finalScrollPosition = Math.max(0, scrollTarget + adjustment);

        window.scrollTo({
          top: finalScrollPosition,
          behavior: behavior
        });
      }
    }
  }

  // Override the initIframeResizer behavior to inject our onMessage handler
  const originalInitIframeResizer = Drupal.behaviors.initIframeResizer;

  Drupal.behaviors.initIframeResizer = {
    attach: function (context, settings) {
      // Only run once per page to avoid duplicate overrides
      once('once-iframe-behavior-override', 'body', context).forEach(function () {
        // First, override the jQuery iFrameResize function
        if ($ && $.fn.iFrameResize) {
          const originalIFrameResize = $.fn.iFrameResize;

          $.fn.iFrameResize = function (options) {
            // Merge our onMessage handler with existing options
            const enhancedOptions = $.extend({}, options || {});
            const originalOnMessage = enhancedOptions.onMessage;

            enhancedOptions.onMessage = function (messageData) {
              // Handle our custom focus messages
              handleFocusMessage(messageData);

              // Call original onMessage if it exists
              if (typeof originalOnMessage === 'function') {
                originalOnMessage(messageData);
              }
            };

            // Call the original iFrameResize with enhanced options
            return originalIFrameResize.call(this, enhancedOptions);
          };
        }

        // Then call the original initIframeResizer behavior
        if (originalInitIframeResizer && originalInitIframeResizer.attach) {
          originalInitIframeResizer.attach(context, settings);
        }
      });
    }
  };

})(jQuery, Drupal);