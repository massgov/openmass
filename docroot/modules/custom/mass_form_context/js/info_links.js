(function (Drupal, drupalSettings, once) {
  'use strict';

  const STORAGE_KEY = 'massFormContext';
  const TTL_MS = 24 * 60 * 60 * 1000; // 24 hours

  function loadStorage() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) {
        return {
          forms: {}
        };
      }
      const data = JSON.parse(raw);
      if (!data || typeof data !== 'object') {
        return {
          forms: {}
        };
      }
      if (!data.forms || typeof data.forms !== 'object') {
        data.forms = {};
      }
      return data;
    }
    catch (e) {
      return {
        forms: {}
      };
    }
  }

  function saveStorage(storage) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(storage));
    }
    catch (e) {
      // Ignore storage errors (quota, privacy settings, etc.).
    }
  }

  function cleanUrlRemovingAllowed(allowed) {
    if (!allowed.length) {
      return;
    }

    var url = new URL(window.location.href);
    var params = new URLSearchParams(url.search);
    var changed = false;

    allowed.forEach(function (key) {
      if (params.has(key)) {
        params.delete(key);
        changed = true;
      }
    });

    if (!changed) {
      return;
    }

    var newSearch = params.toString();
    var cleanUrl =
      url.origin +
      url.pathname +
      (newSearch ? '?' + newSearch : '') +
      url.hash;

    window.history.replaceState({}, '', cleanUrl);
  }

  Drupal.behaviors.massFormContextForwardQueryToFormLinks = {
    attach: function (context) {
      var mfcSettings = drupalSettings.massFormContext || {};
      var settings = mfcSettings.forwardQueryToFormLinks || {};
      if (!settings.enabled) {
        return;
      }

      var allowed = settings.allowedKeys || ['referrer', 'org', 'parentorg', 'site'];

      // 1️⃣ Extract allowed params from current URL.
      var current = new URLSearchParams(window.location.search);
      var filtered = new URLSearchParams();

      current.forEach(function (value, key) {
        if (!allowed.length || allowed.indexOf(key) !== -1) {
          filtered.set(key, value);
        }
      });

      if (!filtered.toString()) {
        // Nothing to store for this page.
        return;
      }

      var now = Date.now();
      var storage = loadStorage();

      // 2️⃣ Find all form links on this Info page and map them to this context.
      var selector = [
        'a[href^="/forms/"]',
        'a[href^="https://www.mass.gov/forms/"]',
        'a[href^="https://mass.gov/forms/"]'
      ].join(', ');

      var links = once(
        'mass-form-context-map-forms',
        selector,
        context
      );

      if (links.length) {
        var paramsString = filtered.toString();

        links.forEach(function (link) {
          try {
            var url = new URL(link.href, window.location.origin);
            var formPath = url.pathname; // e.g. "/forms/foo"

            storage.forms[formPath] = {
              params: paramsString,
              timestamp: now
            };
          }
          catch (e) {
            // Ignore invalid links.
          }
        });

        // Save back to storage.
        saveStorage(storage);
      }

      // 3️⃣ Clean allowed params from current Info URL, keep the rest (analytics, etc.).
      cleanUrlRemovingAllowed(allowed);
    }
  };
})(Drupal, drupalSettings, once);
