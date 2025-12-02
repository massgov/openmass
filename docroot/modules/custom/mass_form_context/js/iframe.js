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

      // 1️⃣ Start with stored context for this specific form path (if any and not expired).
      var storedParams = new URLSearchParams();
      var existing = storage.forms[formPath];

      if (existing && existing.params) {
        var age = now - (existing.timestamp || 0);
        if (age <= TTL_MS) {
          storedParams = new URLSearchParams(existing.params);
        }
        else {
          // Expired → forget it for this form.
          delete storage.forms[formPath];
          saveStorage(storage);
        }
      }

      // 2️⃣ Merge in any allowed params from the Form page URL (direct external → form).
      var urlParams = new URLSearchParams(window.location.search);
      var hasUrlParams = false;

      urlParams.forEach(function (value, key) {
        if (!allowed.length || allowed.indexOf(key) !== -1) {
          storedParams.set(key, value); // URL wins over stored
          hasUrlParams = true;
        }
      });

      // 3️⃣ If URL carried allowed params, update this form's entry and clean only those keys.
      if (hasUrlParams) {
        storage.forms[formPath] = {
          params: storedParams.toString(),
          timestamp: now
        };
        saveStorage(storage);
        cleanUrlRemovingAllowed(allowed);
      }

      // 4️⃣ Build iframe.src from data-src + storedParams for this form.
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
