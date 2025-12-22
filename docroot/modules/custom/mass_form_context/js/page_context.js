(function (Drupal, drupalSettings, once) {
  'use strict';

  var STORAGE_KEY = 'massFormContext';

  // 1 hour TTL (sessionStorage, but TTL prevents stale data inside a long session).
  var TTL_MS = 60 * 60 * 1000;

  // Basic sanitization caps (avoid someone stuffing huge values into storage).
  var MAX_PARAMS = 100;
  var MAX_KEY_LEN = 150;
  var MAX_VAL_LEN = 1000;

  function getBackend() {
    // Prefer sessionStorage (non-persistent).
    return window.sessionStorage;
  }

  function now() {
    return Date.now ? Date.now() : new Date().getTime();
  }

  function safeParse(raw) {
    try {
      return JSON.parse(raw);
    }
    catch (e) {
      return null;
    }
  }

  function emptyStorage() {
    return {
      // Query params seen during session (except ignored keys).
      qs: {},

      // Most recent NON-form page.
      current_page: null,
      current_page_org: null,
      current_page_parent_org: null,

      // One step back.
      prior_page: null,
      prior_page_org: null,
      prior_page_parent_org: null,

      // Two steps back.
      prior_page_2: null,
      prior_page_2_org: null,
      prior_page_2_parent_org: null,

      // Debug: what iframe.js last used.
      last_iframe_context: null,

      // Timestamp for TTL handling.
      _ts: null
    };
  }

  function loadStorage() {
    var backend = getBackend();
    var raw;

    try {
      raw = backend.getItem(STORAGE_KEY);
    }
    catch (e) {
      return emptyStorage();
    }

    if (!raw) {
      return emptyStorage();
    }

    var data = safeParse(raw);
    if (!data || typeof data !== 'object') {
      return emptyStorage();
    }

    // TTL enforcement.
    if (data._ts && (now() - data._ts) > TTL_MS) {
      return emptyStorage();
    }

    // Normalize.
    if (!data.qs || typeof data.qs !== 'object') {
      data.qs = {};
    }
    if (!('current_page' in data)) {
      data.current_page = null;
    }
    if (!('prior_page' in data)) {
      data.prior_page = null;
    }
    if (!('prior_page_2' in data)) {
      data.prior_page_2 = null;
    }
    if (!('last_iframe_context' in data)) {
      data.last_iframe_context = null;
    }

    return data;
  }

  function saveStorage(data) {
    data._ts = now();
    try {
      getBackend().setItem(STORAGE_KEY, JSON.stringify(data));
    }
    catch (e) {
      // Ignore storage errors.
    }
  }

  function getIgnoredKeys() {
    // Provided by Drupal (so we don't hardcode analytics keys).
    // drupalSettings.massFormContext.ignoreKeys = ['utm_source', 'gclid', ...]
    var cfg = (drupalSettings.massFormContext || {}).ignoreKeys || [];
    return Array.isArray(cfg) ? cfg : [];
  }

  function isIgnoredKey(key, ignoredKeys) {
    if (!key) {
      return false;
    }
    var k = String(key).toLowerCase();
    for (var i = 0; i < ignoredKeys.length; i += 1) {
      if (k === String(ignoredKeys[i]).toLowerCase()) {
        return true;
      }
    }
    return false;
  }

  function sanitizeKey(key) {
    var k = String(key || '').trim();
    if (!k) {
      return '';
    }
    // Strip control chars.
    k = k.replace(/[\u0000-\u001F\u007F]/g, '');
    if (k.length > MAX_KEY_LEN) {
      k = k.substring(0, MAX_KEY_LEN);
    }
    return k;
  }

  function sanitizeVal(val) {
    var v = String(val == null ? '' : val);
    // Strip control chars & null bytes.
    v = v.replace(/[\u0000-\u001F\u007F]/g, '');
    if (v.length > MAX_VAL_LEN) {
      v = v.substring(0, MAX_VAL_LEN);
    }
    return v;
  }

  function readMeta(name) {
    // Pull org info from metatags on the page.
    // <meta name="mg_organization" content="foo,bar">
    var el = document.querySelector('meta[name="' + name + '"]');
    if (!el) {
      return '';
    }
    var c = el.getAttribute('content') || '';
    return sanitizeVal(c);
  }

  function getPageOrg() {
    // Prefer mg_organization; fallback to mg_parent_org if needed.
    // (You can decide later if you want both; right now we store both separately.)
    return readMeta('mg_organization');
  }

  function getPageParentOrg() {
    return readMeta('mg_parent_org');
  }

  function captureQueryParams(storage) {
    var ignored = getIgnoredKeys();
    var sp = new URLSearchParams(window.location.search || '');

    var count = 0;
    sp.forEach(function (value, key) {
      if (count >= MAX_PARAMS) {
        return;
      }
      var k = sanitizeKey(key);
      if (!k) {
        return;
      }
      if (isIgnoredKey(k, ignored)) {
        return;
      }
      storage.qs[k] = sanitizeVal(value);
      count += 1;
    });
  }

  Drupal.behaviors.massFormContextPageContext = {
    attach: function (context) {
      // Run once per page load.
      var onceResult = once('mass-form-context-page-context', 'html', context);
      if (!onceResult.length) {
        return;
      }

      // Do NOT run on form pages (we want linking_page to remain the page before the form).
      var cfg = (drupalSettings.massFormContext || {});
      if (cfg.isFormPage) {
        return;
      }

      var storage = loadStorage();

      // Store qs seen on this page (except ignored).
      captureQueryParams(storage);

      // Rotate history (current -> prior -> prior2).
      if (storage.current_page) {
        storage.prior_page_2 = storage.prior_page;
        storage.prior_page_2_org = storage.prior_page_org;
        storage.prior_page_2_parent_org = storage.prior_page_parent_org;

        storage.prior_page = storage.current_page;
        storage.prior_page_org = storage.current_page_org;
        storage.prior_page_parent_org = storage.current_page_parent_org;
      }

      // Set new current page values.
      storage.current_page = window.location.href;
      storage.current_page_org = getPageOrg();
      storage.current_page_parent_org = getPageParentOrg();

      saveStorage(storage);
    }
  };
})(Drupal, drupalSettings, once);
