(function (Drupal, drupalSettings, once) {
  'use strict';

  var STORAGE_KEY = 'massSessionContext';
  var TTL_MS = 60 * 60 * 1000; // 1 hour

  var MAX_PARAMS = 100;
  var MAX_KEY_LEN = 150;
  var MAX_VAL_LEN = 1000;

  function getBackend() {
    return window.sessionStorage;
  }

  function now() {
    return Date.now ? Date.now() : new Date().getTime();
  }

  function stripControlChars(str) {
    var s = String(str == null ? '' : str);
    var out = '';
    for (var i = 0; i < s.length; i += 1) {
      var code = s.charCodeAt(i);
      // Drop ASCII control chars 0x00-0x1F and DEL 0x7F.
      if ((code >= 0 && code <= 31) || code === 127) {
        continue;
      }
      out += s.charAt(i);
    }
    return out;
  }

  function sanitizeKey(key) {
    var k = String(key || '').trim();
    if (!k) {
      return '';
    }
    k = stripControlChars(k);
    if (k.length > MAX_KEY_LEN) {
      k = k.substring(0, MAX_KEY_LEN);
    }
    return k;
  }

  function sanitizeVal(val) {
    var v = stripControlChars(val);
    if (v.length > MAX_VAL_LEN) {
      v = v.substring(0, MAX_VAL_LEN);
    }
    return v;
  }

  function emptyStorage() {
    return {
      qs: {},

      current_page: null,
      current_page_org: null,
      current_page_parent_org: null,

      prior_page: null,
      prior_page_org: null,
      prior_page_parent_org: null,

      prior_page_2: null,
      prior_page_2_org: null,
      prior_page_2_parent_org: null,

      last_iframe_context: null,

      _ts: null
    };
  }

  function safeParse(raw) {
    try {
      return JSON.parse(raw);
    }
    catch (e) {
      return null;
    }
  }

  function loadStorage() {
    var backend = getBackend();
    if (!backend) {
      return emptyStorage();
    }

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

    if (!data.qs || typeof data.qs !== 'object') {
      data.qs = {};
    }

    // Ensure expected keys exist (backwards-compat safe).
    if (!('current_page' in data)) {
      data.current_page = null;
    }
    if (!('current_page_org' in data)) {
      data.current_page_org = null;
    }
    if (!('current_page_parent_org' in data)) {
      data.current_page_parent_org = null;
    }

    if (!('prior_page' in data)) {
      data.prior_page = null;
    }
    if (!('prior_page_org' in data)) {
      data.prior_page_org = null;
    }
    if (!('prior_page_parent_org' in data)) {
      data.prior_page_parent_org = null;
    }

    if (!('prior_page_2' in data)) {
      data.prior_page_2 = null;
    }
    if (!('prior_page_2_org' in data)) {
      data.prior_page_2_org = null;
    }
    if (!('prior_page_2_parent_org' in data)) {
      data.prior_page_2_parent_org = null;
    }

    if (!('last_iframe_context' in data)) {
      data.last_iframe_context = null;
    }

    return data;
  }

  function saveStorage(data) {
    data._ts = now();
    getBackend().setItem(STORAGE_KEY, JSON.stringify(data));
  }

  function getIgnoredKeys() {
    var cfg = ((drupalSettings.massSessionContext || {}).ignoreKeys) || [];
    return Array.isArray(cfg) ? cfg : [];
  }

  function isIgnoredKey(key, ignoredKeys) {
    var k = String(key || '').toLowerCase();
    return ignoredKeys.map(function (i) {
      return String(i).toLowerCase();
    }).indexOf(k) !== -1;
  }

  function readMeta(name) {
    var el = document.querySelector('meta[name="' + name + '"]');
    if (!el) {
      return '';
    }
    return sanitizeVal(el.getAttribute('content') || '');
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
      if (!k || isIgnoredKey(k, ignored)) {
        return;
      }
      storage.qs[k] = sanitizeVal(value);
      count += 1;
    });
  }

  Drupal.behaviors.massSessionContextPageContext = {
    attach: function (context) {
      var onceResult = once('mass-session-context-page-context', 'html', context);
      if (!onceResult.length) {
        return;
      }

      var cfg = (drupalSettings.massSessionContext || {});
      var isFormPage = !!cfg.isFormPage;

      var storage = loadStorage();

      // 1) Capture query params
      captureQueryParams(storage);

      var thisUrl = window.location.href;

      // 2) Rotate ONLY if NOT a form page
      if (!isFormPage && storage.current_page && storage.current_page !== thisUrl) {

        storage.prior_page_2 = storage.prior_page || null;
        storage.prior_page_2_org = storage.prior_page_org || null;
        storage.prior_page_2_parent_org = storage.prior_page_parent_org || null;

        storage.prior_page = storage.current_page || null;
        storage.prior_page_org = storage.current_page_org || null;
        storage.prior_page_parent_org = storage.current_page_parent_org || null;
      }

      // 3) Always set current page (INCLUDING form pages)
      storage.current_page = thisUrl;
      storage.current_page_org = readMeta('mg_organization');
      storage.current_page_parent_org = readMeta('mg_parent_org');

      saveStorage(storage);
    }
  };
})(Drupal, drupalSettings, once);
