(function ($, Drupal, iframeResizerSettings) {
  'use strict';

  // Set up the iFrame Resizer library's options.
  var options = {};
  if (iframeResizerSettings.advanced.override_defaults) {
    if (iframeResizerSettings.advanced.options.maxHeight === -1) {
      iframeResizerSettings.advanced.options.maxHeight = Infinity;
    }

    if (iframeResizerSettings.advanced.options.maxWidth === -1) {
      iframeResizerSettings.advanced.options.maxWidth = Infinity;
    }

    options = iframeResizerSettings.advanced.options;
  }

  const focusOptions = {
    log: false,
    checkOrigin: false,
    messageCallback: function (messageData) {
      const { message, iframe } = messageData;

      if (message.type === 'scrollToFocus') {
        // iframe position in the parent page
        const iframeTop = iframe.getBoundingClientRect().top + window.scrollY;

        // message.offset is the offset inside the iframe
        const scrollTarget = iframeTop + message.offset;
        const adjustment = -250;

        window.scrollTo({
          top: scrollTarget + adjustment,
          behavior: 'smooth'
        });
      }
    }
  }

  const newOptions = {...options, ...focusOptions};

  Drupal.behaviors.initIframeResizer = {
    attach: function (context, settings) {
      alert('test')
      var selector = 'iframe';
      if (typeof settings.iframeResizer.advanced.targetSelectors !== 'undefined') {
        selector = settings.iframeResizer.advanced.targetSelectors;
      }

      $(selector, context).iFrameResize(newOptions);
    }
  };
})(jQuery, Drupal, drupalSettings.iframeResizer);
