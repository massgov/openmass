(function (Drupal, drupalSettings, once) {
  'use strict';

  var STORAGE_KEY = 'massFormContext';

  // Basic sanitization caps.
  var MAX_PARAMS = 100;
  var MAX_KEY_LEN = 150;
  var MAX_VAL_LEN = 1000;

  function getIgnoredKeys() {
    var s = (drupalSettings.massFormContext || {}).ignoreKeys || [];
    // Always ignore "utm_*" via prefix rule too (see isIgnoredKey()).
    return Array.isArray(s) ? s : [];
  }

  function isIgnoredKey(key, ignoredKeys) {
    if (!key) {
      return true;
    }
    // Treat any utm_* as analytics without enumerating.
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
      // Ignore (quota, privacy settings, etc).
    }
  }

  function getCleanCurrentUrl() {
    // Store cleaned URL for safety (no querystring), but DO NOT change the browser URL.
    return window.location.origin + window.location.pathname + window.location.hash;
  }

  function getOrgFromMeta() {
    // Prefer parent org.
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

  Drupal.behaviors.massFormContextPageContext = {
    attach: function (context) {
      var onceResult = once('mass-form-context-page', 'html', context);
      if (!onceResult.length) {
        return;
      }

      var ignoredKeys = getIgnoredKeys();
      var storage = loadStorage();

      // 1) Capture all query params except ignored analytics keys.
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

        storage.qs[key] = value; // overwrite existing keys
        count += 1;
      });

      // 2) Rotate current → prior (overwrite).
      if (storage.current_page || storage.current_page_org) {
        storage.prior_page = storage.current_page || null;
        storage.prior_page_org = storage.current_page_org || null;
      }

      // 3) Set current page + org (cleaned URL stored, but browser URL stays unchanged).
      storage.current_page = getCleanCurrentUrl();
      storage.current_page_org = getOrgFromMeta();

      saveStorage(storage);
    }
  };
})(Drupal, drupalSettings, once);
