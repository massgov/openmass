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

  Drupal.behaviors.massFormContextGravityFormsIframe = {
    attach: function (context) {
      var iframes = once(
        'mass-form-context-gf-iframe',
        'iframe.js-gf-iframe[data-src]',
        context
      );
      if (!iframes.length) {
        return;
      }

      var mfcSettings = drupalSettings.massFormContext || {};
      var iframeSettings = mfcSettings.iframe || {};
      var allowed = iframeSettings.allowedKeys || ['referrer', 'org', 'parentorg', 'site'];

      var now = Date.now();
      var storage = loadStorage();
      var formPath = window.location.pathname; // e.g. "/forms/foo"

      // --- 1) Check existing per-form mapping (we may use it later if URL has no params) ---
      var existing = storage.forms[formPath];
      if (existing && existing.params) {
        var age = now - (existing.timestamp || 0);
        if (age > TTL_MS) {
          // Expired → remove.
          delete storage.forms[formPath];
          existing = null;
          saveStorage(storage);
        }
      }

      // --- 2) Read allowed params from the Form page URL ---
      var urlParams = new URLSearchParams(window.location.search);
      var urlFiltered = new URLSearchParams();
      var hasUrlParams = false;

      urlParams.forEach(function (value, key) {
        if (!allowed.length || allowed.indexOf(key) !== -1) {
          urlFiltered.set(key, value);
          hasUrlParams = true;
        }
      });

      // --- 3) Decide source of truth for storedParams ---
      var storedParams = new URLSearchParams();

      if (hasUrlParams) {
        // CASE A: URL has allowed params → URL is the ONLY source of truth.
        storedParams = urlFiltered;

        // Overwrite per-form mapping with these exact params.
        storage.forms[formPath] = {
          params: storedParams.toString(),
          timestamp: now
        };
        saveStorage(storage);

        // Clean allowed params from the visible URL.
        cleanUrlRemovingAllowed(allowed);
      }
      else if (existing && existing.params) {
        // CASE B: No URL params, use existing per-form mapping (already validated for TTL).
        storedParams = new URLSearchParams(existing.params);
      }
      else if (storage.lastPage && storage.lastPage.params) {
        // CASE C: No URL params and no mapping → fall back to lastPage (start page).
        var lastAge = now - (storage.lastPage.timestamp || 0);
        if (lastAge <= TTL_MS) {
          storedParams = new URLSearchParams(storage.lastPage.params);

          // Persist this context as the per-form mapping for future visits.
          storage.forms[formPath] = {
            params: storedParams.toString(),
            timestamp: now
          };
          saveStorage(storage);
        }
        else {
          storage.lastPage = null;
          saveStorage(storage);
        }
      }
      // CASE D: No URL params, no mapping, no lastPage → storedParams stays empty.

      // --- 4) Build iframe.src from data-src + storedParams for this form. ---
      iframes.forEach(function (iframe) {
        var baseSrc = iframe.getAttribute('data-src');
        if (!baseSrc) {
          return;
        }

        var url;
        try {
          url = new URL(baseSrc, window.location.origin);
        }
        catch (e) {
          return;
        }

        storedParams.forEach(function (value, key) {
          if (!allowed.length || allowed.indexOf(key) !== -1) {
            url.searchParams.set(key, value);
          }
        });

        iframe.setAttribute('src', url.toString());
        iframe.removeAttribute('data-src');
      });
    }
  };
})(Drupal, drupalSettings, once);
