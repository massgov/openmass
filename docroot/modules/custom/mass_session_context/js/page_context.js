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

      linking_page: null,
      linking_page_org: null,
      linking_page_parent_org: null,

      previous_page: null,
      previous_page_org: null,
      previous_page_parent_org: null,

      previous_page_2: null,
      previous_page_2_org: null,
      previous_page_2_parent_org: null,

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

    // TTL
    if (data._ts && (now() - data._ts) > TTL_MS) {
      return emptyStorage();
    }

    if (!data.qs || typeof data.qs !== 'object') {
      data.qs = {};
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
    var k = String(key).toLowerCase();
    return ignoredKeys.indexOf(k) !== -1;
  }

  function readMeta(name) {
    var el = document.querySelector('meta[name="' + name + '"]');
    return el ? sanitizeVal(el.getAttribute('content') || '') : '';
  }

  function canonicalizeUrl(urlString) {
    try {
      var u = new URL(urlString, window.location.href);
      return u.origin + u.pathname;
    }
    catch (e) {
      // Best-effort fallback: strip query/hash.
      return String(urlString || '').split('#')[0].split('?')[0];
    }
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

  Drupal.behaviors.massSessionContextPageContext = {
    attach: function (context) {
      var onceResult = once('mass-session-context-page', 'html', context);
      if (!onceResult.length) {
        return;
      }

      var storage = loadStorage();

      // 1) Capture query params
      captureQueryParams(storage);

      var thisUrl = window.location.href;

      var thisKey = canonicalizeUrl(thisUrl);
      var currentKey = storage.linking_page ? canonicalizeUrl(storage.linking_page) : null;

      // 2) Rotate history ONLY when the canonical page changes (ignore query/hash changes)
      if (currentKey && currentKey !== thisKey) {

        storage.previous_page_2 = storage.previous_page || null;
        storage.previous_page_2_org = storage.previous_page_org || null;
        storage.previous_page_2_parent_org = storage.previous_page_parent_org || null;

        storage.previous_page = storage.linking_page || null;
        storage.previous_page_org = storage.linking_page_org || null;
        storage.previous_page_parent_org = storage.linking_page_parent_org || null;
      }

      // 3) Set new current page
      storage.linking_page = thisUrl;
      storage.linking_page_org = readMeta('mg_organization');
      storage.linking_page_parent_org = readMeta('mg_parent_org');

      saveStorage(storage);
    }
  };
})(Drupal, drupalSettings, once);
