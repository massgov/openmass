(function ($, Drupal) {
  'use strict';

  // Safe scrolling using requestAnimationFrame for all devices
  function scrollToPositionSafely(targetPosition, container) {
    let start = null;
    const startPosition = container === window ? window.pageYOffset : container.scrollTop;
    const distance = targetPosition - startPosition;
    const duration = 400; // Smooth duration for all devices

    function animateScroll(timestamp) {
      if (!start) start = timestamp;
      const progress = Math.min((timestamp - start) / duration, 1);
      
      // Use easeOutCubic for smooth animation
      const easeProgress = 1 - Math.pow(1 - progress, 3);
      const currentPosition = startPosition + (distance * easeProgress);

      if (container === window) {
        window.scrollTo(0, currentPosition);
      } else {
        container.scrollTop = currentPosition;
      }

      if (progress < 1) {
        requestAnimationFrame(animateScroll);
      }
    }

    requestAnimationFrame(animateScroll);
  }

  // Function to handle focus messages from iframes
  function handleFocusMessage(messageData) {
    // Check if this is our custom focus message - handle both message structures
    const message = messageData.message || messageData;
    const iframe = messageData.iframe;

    if (message && message.type === 'scrollToFocus') {

      if (iframe) {
        // Check if the iframe is within a modal
        const modal = iframe.closest('.tingle-modal-box__content');

        if (modal) {
          // Handle focus within modal - scroll the modal content
          const modalRect = modal.getBoundingClientRect();
          const iframeRect = iframe.getBoundingClientRect();
          const relativeIframeTop = iframeRect.top - modalRect.top;
          const scrollTarget = modal.scrollTop + relativeIframeTop + (message.offset || 0);
          const adjustment = -250; // Less adjustment needed within modal

          const finalScrollPosition = Math.max(0, scrollTarget + adjustment);

          // Use Android-safe scrolling
          scrollToPositionSafely(finalScrollPosition, modal);
        }
        else {
          // Handle focus in regular page iframe
          const iframeTop = iframe.getBoundingClientRect().top + window.scrollY;
          const scrollTarget = iframeTop + (message.offset || 0);
          const adjustment = -250;
          const finalScrollPosition = Math.max(0, scrollTarget + adjustment);

          // Use Android-safe scrolling
          scrollToPositionSafely(finalScrollPosition, window);
        }
      }
      else {
        console.warn('No iframe found in message data');
      }
    }
    else {
      console.warn('Message is not a scrollToFocus type:', message);
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

