(function (Drupal, drupalSettings, once) {
  'use strict';

  var STORAGE_KEY = 'massFormContext';
  var TTL_MS = 60 * 60 * 1000; // 1 hour

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

  function appendIfValue(params, key, value) {
    if (value !== null && value !== '') {
      params.set(key, sanitizeVal(value));
    }
  }

  function buildFinalParams(storage) {
    var finalParams = new URLSearchParams();

    // 1) Add ALL stored querystring params (minus ignored keys already filtered in page_context.js).
    Object.keys(storage.qs || {}).forEach(function (k) {
      var key = sanitizeKey(k);
      if (!key) {
        return;
      }
      finalParams.set(key, sanitizeVal(storage.qs[k]));
    });

    // 2) Add Joe's context fields using storage naming as the source of truth:
    // linking_page = current_page (page before form)
    appendIfValue(finalParams, 'linking_page', storage.current_page);
    appendIfValue(finalParams, 'linking_page_org', storage.current_page_org);
    appendIfValue(finalParams, 'linking_page_parent_org', storage.current_page_parent_org);

    // previous_page = prior_page
    appendIfValue(finalParams, 'previous_page', storage.prior_page);
    appendIfValue(finalParams, 'previous_page_org', storage.prior_page_org);
    appendIfValue(finalParams, 'previous_page_parent_org', storage.prior_page_parent_org);

    // previous_page2 = prior_page_2
    appendIfValue(finalParams, 'previous_page2', storage.prior_page_2);
    appendIfValue(finalParams, 'previous_page2_org', storage.prior_page_2_org);
    appendIfValue(finalParams, 'previous_page2_parent_org', storage.prior_page_2_parent_org);

    return finalParams;
  }

  function applyParamsToIframe(iframe, finalParams) {
    var baseSrc = iframe.getAttribute('data-src');
    if (!baseSrc) {
      return;
    }

    var url;
    try {
      url = new URL(baseSrc);
    }
    catch (e) {
      try {
        url = new URL(baseSrc, window.location.origin);
      }
      catch (e2) {
        return;
      }
    }

    finalParams.forEach(function (value, key) {
      url.searchParams.set(key, value);
    });

    iframe.setAttribute('src', url.toString());
    iframe.removeAttribute('data-src');
  }

  Drupal.behaviors.massFormContextIframe = {
    attach: function (context) {
      var onceResult = once('mass-form-context-iframe', 'html', context);
      if (!onceResult.length) {
        return;
      }

      // Only target lazy iframes.
      var iframes = Array.prototype.slice.call(document.querySelectorAll('iframe[data-src]'));
      if (!iframes.length) {
        return;
      }

      var storage = loadStorage();
      var finalParams = buildFinalParams(storage);

      // Debug: store what we are about to send to the iframe.
      var debugObj = {};
      finalParams.forEach(function (value, key) {
        debugObj[key] = value;
      });
      storage.last_iframe_context = debugObj;
      saveStorage(storage);

      iframes.forEach(function (iframe) {
        applyParamsToIframe(iframe, finalParams);
      });
    }
  };
})(Drupal, drupalSettings, once);
