(function (Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.massFormContextGravityFormsIframe = {
    attach: function (context) {
      // Target only our Gravity Forms iframes that have a base URL in data-src.
      var iframes = once(
        'mass-form-context-gf-iframe',
        'iframe.js-gf-iframe[data-src]',
        context
      );
      if (!iframes.length) {
        return;
      }

      var settings = drupalSettings.massFormContext || {};
      var cfg = settings.iframe || {};
      var allowedKeys = cfg.allowedKeys || ['referrer', 'org', 'parentorg', 'site'];

      var search = window.location.search || '';
      var params = new URLSearchParams(search);
      var filteredParams = new URLSearchParams();

      params.forEach(function (value, key) {
        if (!allowedKeys.length || allowedKeys.indexOf(key) !== -1) {
          filteredParams.set(key, value);
        }
      });

      iframes.forEach(function (iframe) {
        var baseSrc = iframe.getAttribute('data-src');
        if (!baseSrc) {
          return;
        }

        var url;
        try {
          url = new URL(baseSrc, window.location.origin);
        } catch (e) {
          // Invalid URL – bail.
          return;
        }

        if (filteredParams.toString()) {
          filteredParams.forEach(function (value, key) {
            url.searchParams.set(key, value);
          });
        }

        // Always set src from JS, overriding any empty/about:blank/etc.
        iframe.setAttribute('src', url.toString());
      });
    }
  };
})(Drupal, drupalSettings, once);
