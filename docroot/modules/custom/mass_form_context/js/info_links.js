(function (Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.massFormContextForwardQueryToFormLinks = {
    attach: function (context) {
      var settings = drupalSettings.massFormContext || {};
      var cfg = settings.forwardQueryToFormLinks || {};

      console.log(settings);
      if (!cfg.enabled) {
        return;
      }

      var allowedKeys = cfg.allowedKeys || ['referrer', 'org', 'parentorg', 'site'];
      var search = window.location.search || '';
      if (!search) {
        return;
      }

      var currentParams = new URLSearchParams(search);
      var filteredParams = new URLSearchParams();

      currentParams.forEach(function (value, key) {
        if (!allowedKeys.length || allowedKeys.indexOf(key) !== -1) {
          filteredParams.set(key, value);
        }
      });

      if (!filteredParams.toString()) {
        return;
      }

      // Target *all* form links:
      // - internal relative:   /forms/...
      // - fully qualified:     https://www.mass.gov/forms/...
      var selector = [
        'a[href^="/forms/"]',
        'a[href^="https://www.mass.gov/forms/"]',
        'a[href^="https://mass.gov/forms/"]'
      ].join(', ');

      var links = once('mass-form-context-forward-query', selector, context);

      links.forEach(function (link) {
        try {
          var url = new URL(link.href, window.location.origin);

          // Merge / overwrite only our allowed keys; keep anything else already
          // on the href (if editors add query params manually).
          filteredParams.forEach(function (value, key) {
            url.searchParams.set(key, value);
          });

          link.href = url.toString();
        } catch (e) {
          // Ignore invalid hrefs.
        }
      });
    }
  };
})(Drupal, drupalSettings, once);
