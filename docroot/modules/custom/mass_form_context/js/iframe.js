(function (Drupal, drupalSettings, once) {
  'use strict';

  var STORAGE_KEY = 'massFormContext';

  // Reset if last view was more than 1 hour ago.
  var RESET_AFTER_MS = 60 * 60 * 1000;

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
    // Treat utm_* as analytics without enumerating.
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

      prior_page_1: null,
      prior_page_org_1: null,

      prior_page_2: null,
      prior_page_org_2: null,

      // Used to decide whether to reset everything after inactivity.
      last_view_ts: null,

      // Debug object (what was last sent to the iframe).
      last_iframe_context: null
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

    // Ensure last_iframe_context is either null or an object.
    if (data.last_iframe_context !== null && typeof data.last_iframe_context !== 'object') {
      data.last_iframe_context = null;
    }

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
      // Ignore storage errors (quota/privacy).
    }
  }

  function shouldReset(storage, nowTs) {
    var last = storage && storage.last_view_ts;
    return (typeof last === 'number') && ((nowTs - last) > RESET_AFTER_MS);
  }

  function appendIfValue(sp, key, value) {
    if (value !== null && value !== '') {
      sp.set(key, value);
    }
  }

  function getCleanCurrentUrl() {
    // Cleaned URL for storage/iframe (no querystring). Do NOT change browser URL.
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

  function captureQueryParamsIntoStorage(storage, ignoredKeys) {
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
  }

  function buildIframeContext(storage, linkingPage, linkingPageOrg, previousPage, previousPageOrg, previousPage2, previousPageOrg2, formPage, formPageOrg) {
    // Debug object you can inspect in sessionStorage.
    return {
      // Custom params captured during the session (excluding analytics).
      qs: storage.qs || {},

      // Page context.
      linking_page: linkingPage || '',
      linking_page_org: linkingPageOrg || '',
      previous_page: previousPage || '',
      previous_page_org: previousPageOrg || '',

      // One more page back (new).
      previous_page_2: previousPage2 || '',
      previous_page_org_2: previousPageOrg2 || '',

      form_page: formPage || '',
      form_page_org: formPageOrg || '',

      // Helpful extra debug info.
      built_at: Date.now()
    };
  }

  function buildFinalParamsFromContext(ctx) {
    var sp = new URLSearchParams();

    // 1) All custom params.
    Object.keys(ctx.qs || {}).forEach(function (k) {
      sp.set(k, ctx.qs[k]);
    });

    // 2) Context params (existing).
    appendIfValue(sp, 'linking_page', ctx.linking_page);
    appendIfValue(sp, 'linking_page_org', ctx.linking_page_org);
    appendIfValue(sp, 'previous_page', ctx.previous_page);
    appendIfValue(sp, 'previous_page_org', ctx.previous_page_org);
    appendIfValue(sp, 'previous_page_2', ctx.previous_page_2);
    appendIfValue(sp, 'previous_page_org_2', ctx.previous_page_org_2);

    return sp;
  }

  Drupal.behaviors.massFormContextGravityFormsIframe = {
    attach: function (context) {
      var iframes = once('mass-form-context-gf-iframe', 'iframe.js-gf-iframe[data-src]', context);
      if (!iframes.length) {
        return;
      }

      var nowTs = Date.now();
      var ignoredKeys = getIgnoredKeys();
      var storage = loadStorage();

      // Reset everything if more than an hour has passed since the last page view.
      if (shouldReset(storage, nowTs)) {
        storage = defaultStorage();
      }

      // Snapshot the “pages before form” BEFORE we mutate storage.
      // These are set by page_context.js on non-form pages.
      var linkingPage = storage.current_page;
      var linkingPageOrg = storage.current_page_org;
      var previousPage = storage.prior_page_1;
      var previousPageOrg = storage.prior_page_org_1;
      var previousPage2 = storage.prior_page_2;
      var previousPageOrg2 = storage.prior_page_org_2;

      // Always compute the form page (clean URL + org).
      var formPage = getCleanCurrentUrl();
      var formPageOrg = getOrgFromMeta();

      // 1) Capture query params present on the FORM page URL (external → form),
      // except analytics keys. (No URL cleanup.)
      captureQueryParamsIntoStorage(storage, ignoredKeys);

      // 2) Update storage so current_page becomes the FORM page itself,
      // shifting history ONLY if we are not already on this same form page (avoid rotate on refresh).
      if (storage.current_page !== formPage) {
        if (storage.current_page || storage.current_page_org) {
          storage.prior_page_2 = storage.prior_page_1 || null;
          storage.prior_page_org_2 = storage.prior_page_org_1 || null;

          storage.prior_page_1 = storage.current_page || null;
          storage.prior_page_org_1 = storage.current_page_org || null;
        }

        storage.current_page = formPage;
        storage.current_page_org = formPageOrg;
      } else {
        // Still ensure org is current in case meta differs.
        storage.current_page_org = formPageOrg;
      }

      // Update last view timestamp for 1-hour reset logic.
      storage.last_view_ts = nowTs;

      // 3) Build and store a debug context object that matches what we’ll send to iframe.
      storage.last_iframe_context = buildIframeContext(
        storage,
        linkingPage,
        linkingPageOrg,
        previousPage,
        previousPageOrg,
        previousPage2,
        previousPageOrg2,
        formPage,
        formPageOrg
      );

      saveStorage(storage);

      // 4) Build final params FROM storage.last_iframe_context (single source of truth).
      var finalParams = buildFinalParamsFromContext(storage.last_iframe_context || { qs: {} });

      // 5) Apply to iframe src and remove data-src after use.
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
