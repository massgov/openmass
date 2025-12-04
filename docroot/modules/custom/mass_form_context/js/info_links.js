(function (Drupal, drupalSettings, once) {
  'use strict';

  var STORAGE_KEY = 'massFormContext';
  var TTL_MS = 24 * 60 * 60 * 1000; // 24 hours

  function loadStorage() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) {
        return {forms: {}, lastPage: null};
      }
      var data = JSON.parse(raw);
      if (!data || typeof data !== 'object') {
        return {forms: {}, lastPage: null};
      }
      if (!data.forms || typeof data.forms !== 'object') {
        data.forms = {};
      }
      if (!data.lastPage || typeof data.lastPage !== 'object') {
        data.lastPage = null;
      }
      return data;
    }
    catch (e) {
      return {forms: {}, lastPage: null};
    }
  }

  function saveStorage(storage) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(storage));
    }
    catch (e) {
      // Ignore storage errors.
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
      var now = Date.now();
      var storage = loadStorage();

      // 1️⃣ Start with allowed params from current URL.
      var current = new URLSearchParams(window.location.search);
      var filtered = new URLSearchParams();

      current.forEach(function (value, key) {
        if (!allowed.length || allowed.indexOf(key) !== -1) {
          filtered.set(key, value);
        }
      });

      // 2️⃣ If no URL params, fall back to lastPage context (from a start page).
      if (!filtered.toString() && storage.lastPage && storage.lastPage.params) {
        var age = now - (storage.lastPage.timestamp || 0);
        if (age <= TTL_MS) {
          filtered = new URLSearchParams(storage.lastPage.params);
        }
        else {
          storage.lastPage = null;
          saveStorage(storage);
        }
      }

      if (!filtered.toString()) {
        // No context available for this Info page.
        return;
      }

      // 3️⃣ Map this context to all /forms/... links on the Info page.
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

        saveStorage(storage);
      }

      // 4️⃣ Clean only allowed params from the Info URL (if any).
      cleanUrlRemovingAllowed(allowed);
    }
  };
})(Drupal, drupalSettings, once);
