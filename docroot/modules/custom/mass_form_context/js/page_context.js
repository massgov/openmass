(function (Drupal, drupalSettings, once) {
  'use strict';

  var STORAGE_KEY = 'massFormContext';

  // Reset if last view was more than 1 hour ago.
  var RESET_AFTER_MS = 60 * 60 * 1000;

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

    // Explicitly ignore common analytics params.
    if (key === '_ga' || key === '_gl') {
      return true;
    }

    return ignoredKeys.indexOf(key) !== -1;
  }

  function defaultStorage() {
    return {
      qs: {},

      current_page: null,
      current_page_org: null,
      current_page_parent_org: null,

      prior_page_1: null,
      prior_page_org_1: null,
      prior_page_parent_org_1: null,

      prior_page_2: null,
      prior_page_org_2: null,
      prior_page_parent_org_2: null,

      // Single timestamp used for 1-hour reset.
      last_view_ts: null
    };
  }

  function normalizeStorage(data) {
    if (!data || typeof data !== 'object') {
      return defaultStorage();
    }
    if (!data.qs || typeof data.qs !== 'object') {
      data.qs = {};
    }

    // Ensure expected keys exist (shape hardening).
    var d = defaultStorage();
    Object.keys(d).forEach(function (k) {
      if (!(k in data)) {
        data[k] = d[k];
      }
    });

    return data;
  }

  function loadStorage() {
    try {
      var raw = sessionStorage.getItem(STORAGE_KEY);
      if (!raw) {
        return defaultStorage();
      }
      return normalizeStorage(JSON.parse(raw));
    }
    catch (e) {
      return defaultStorage();
    }
  }

  function saveStorage(storage) {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(storage));
    }
    catch (e) {
      // Ignore (quota, privacy settings, etc).
    }
  }

  function shouldReset(storage, nowTs) {
    var last = storage && storage.last_view_ts;
    return (typeof last === 'number') && ((nowTs - last) > RESET_AFTER_MS);
  }

  function getCleanCurrentUrl() {
    // Store cleaned URL for safety (no querystring), but DO NOT change the browser URL.
    return window.location.origin + window.location.pathname + window.location.hash;
  }

  function getOrgFromMeta() {
    var org = document.querySelector('meta[name="mg_organization"]');
    if (org && org.getAttribute('content')) {
      return org.getAttribute('content');
    }
    return '';
  }

  function getParentOrgFromMeta() {
    var parent = document.querySelector('meta[name="mg_parent_org"]');
    if (parent && parent.getAttribute('content')) {
      return parent.getAttribute('content');
    }
    return '';
  }

  Drupal.behaviors.massFormContextPageContext = {
    attach: function (context) {
      var onceResult = once('mass-form-context-page', 'html', context);
      if (!onceResult.length) {
        return;
      }

      var nowTs = Date.now();
      var ignoredKeys = getIgnoredKeys();
      var storage = loadStorage();

      // Reset everything if more than an hour has passed since the last page view.
      if (shouldReset(storage, nowTs)) {
        storage = defaultStorage();
      }

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

      // 2) Shift history back:
      //    current -> prior_page_1
      //    prior_page_1 -> prior_page_2
      if (storage.current_page || storage.current_page_org) {
        storage.prior_page_2 = storage.prior_page_1 || null;
        storage.prior_page_org_2 = storage.prior_page_org_1 || null;
        storage.prior_page_parent_org_2 = storage.prior_page_parent_org_1 || null;

        storage.prior_page_1 = storage.current_page || null;
        storage.prior_page_org_1 = storage.current_page_org || null;
        storage.prior_page_parent_org_1 = storage.current_page_parent_org || null;
      }

      // 3) Set current page + org (cleaned URL stored, browser URL unchanged).
      storage.current_page = getCleanCurrentUrl();
      storage.current_page_org = getOrgFromMeta();
      storage.current_page_parent_org = getParentOrgFromMeta();

      // Update last view timestamp for 1-hour reset logic.
      storage.last_view_ts = nowTs;

      saveStorage(storage);
    }
  };
})(Drupal, drupalSettings, once);
