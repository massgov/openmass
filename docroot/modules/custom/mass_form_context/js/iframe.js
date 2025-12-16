(function (Drupal, drupalSettings, once) {
  'use strict';

  var STORAGE_KEY = 'massFormContext';

  var MAX_PARAMS = 100;
  var MAX_KEY_LEN = 150;
  var MAX_VAL_LEN = 1000;

  function getIgnoredKeys() {
    var s = (drupalSettings.massFormContext || {}).ignoreKeys || [];
    return Array.isArray(s) ? s : [];
  }

  function isIgnoredKey(key, ignoredKeys) {
    if (!key) {
      return true;
    }
    if (key.indexOf('utm_') === 0) {
      return true;
    }
    return ignoredKeys.indexOf(key) !== -1;
  }

  function loadStorage() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) {
        return {
          qs: {},
          current_page: null,
          current_page_org: null,
          prior_page: null,
          prior_page_org: null
        };
      }
      var data = JSON.parse(raw);
      if (!data || typeof data !== 'object') {
        throw new Error('bad storage');
      }
      if (!data.qs || typeof data.qs !== 'object') {
        data.qs = {};
      }
      return data;
    }
    catch (e) {
      return {
        qs: {},
        current_page: null,
        current_page_org: null,
        prior_page: null,
        prior_page_org: null
      };
    }
  }

  function saveStorage(storage) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(storage));
    }
    catch (e) {
      // Ignore storage errors (quota/privacy).
    }
  }

  function appendIfValue(sp, key, value) {
    if (value !== null && value !== '') {
      sp.set(key, value);
    }
  }

  function getCleanCurrentUrl() {
    // Store cleaned URL for the form page (no querystring), but do NOT alter the browser URL.
    return window.location.origin + window.location.pathname + window.location.hash;
  }

  function getOrgFromMeta() {
    var parent = document.querySelector('meta[name="mg_parent_org"]');
    if (parent && parent.getAttribute('content')) {
      return parent.getAttribute('content');
    }
    var org = document.querySelector('meta[name="mg_organization"]');
    if (org && org.getAttribute('content')) {
      return org.getAttribute('content');
    }
    return '';
  }

  Drupal.behaviors.massFormContextGravityFormsIframe = {
    attach: function (context) {
      var iframes = once('mass-form-context-gf-iframe', 'iframe.js-gf-iframe[data-src]', context);
      if (!iframes.length) {
        return;
      }

      var ignoredKeys = getIgnoredKeys();
      var storage = loadStorage();

      // Snapshot the "two pages before form" BEFORE we update storage to the form page.
      var linkingPage = storage.current_page;
      var linkingPageOrg = storage.current_page_org;
      var previousPage = storage.prior_page;
      var previousPageOrg = storage.prior_page_org;

      // 1) Capture query params present on the FORM page URL (external → form),
      // except analytics keys. (No URL cleanup.)
      var seen = new URLSearchParams(window.location.search);
      var count = 0;

      seen.forEach(function (value, key) {
        if (count >= MAX_PARAMS) {
          return;
        }
        if (isIgnoredKey(key, ignoredKeys)) {
          return;
        }
        if (!key || key.length > MAX_KEY_LEN) {
          return;
        }
        if (typeof value !== 'string') {
          return;
        }
        if (value.length > MAX_VAL_LEN) {
          value = value.slice(0, MAX_VAL_LEN);
        }

        storage.qs[key] = value;
        count += 1;
      });

      // 2) Update localStorage so "current_page" becomes the FORM page itself.
      // This satisfies the requirement that storage is updated on form pages.
      var formPage = getCleanCurrentUrl();
      var formPageOrg = getOrgFromMeta();

      if (storage.current_page || storage.current_page_org) {
        storage.prior_page = storage.current_page || null;
        storage.prior_page_org = storage.current_page_org || null;
      }
      storage.current_page = formPage;
      storage.current_page_org = formPageOrg;

      saveStorage(storage);

      // 3) Build final params: all stored qs + page context vars.
      var finalParams = new URLSearchParams();

      Object.keys(storage.qs || {}).forEach(function (k) {
        finalParams.set(k, storage.qs[k]);
      });

      // What linked to the form (the page before the form).
      appendIfValue(finalParams, 'linking_page', linkingPage);
      appendIfValue(finalParams, 'linking_page_org', linkingPageOrg);

      // The page before the linking page.
      appendIfValue(finalParams, 'previous_page', previousPage);
      appendIfValue(finalParams, 'previous_page_org', previousPageOrg);

      // The form page itself (new requirement).
      appendIfValue(finalParams, 'form_page', formPage);
      appendIfValue(finalParams, 'form_page_org', formPageOrg);

      // 4) Apply to iframe src and remove data-src after use.
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

        finalParams.forEach(function (value, key) {
          url.searchParams.set(key, value);
        });

        iframe.setAttribute('src', url.toString());
        iframe.removeAttribute('data-src');
      });
    }
  };
})(Drupal, drupalSettings, once);
