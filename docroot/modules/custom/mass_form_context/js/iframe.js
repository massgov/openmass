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
    // Treat utm_* as analytics without enumerating.
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
          prior_page_org: null,
          last_iframe_context: null
        };
      }
      var data = JSON.parse(raw);
      if (!data || typeof data !== 'object') {
        throw new Error('bad storage');
      }
      if (!data.qs || typeof data.qs !== 'object') {
        data.qs = {};
      }
      if (!Object.prototype.hasOwnProperty.call(data, 'last_iframe_context') || typeof data.last_iframe_context !== 'object') {
        data.last_iframe_context = null;
      }
      return data;
    }
    catch (e) {
      return {
        qs: {},
        current_page: null,
        current_page_org: null,
        prior_page: null,
        prior_page_org: null,
        last_iframe_context: null
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

  function buildIframeContext(storage, linkingPage, linkingPageOrg, previousPage, previousPageOrg, formPage, formPageOrg) {
    // This is the debug object you’ll inspect in localStorage.
    return {
      // Custom params captured during the session (excluding analytics).
      qs: storage.qs || {},

      // Page context (naming matches meaning).
      linking_page: linkingPage || '',
      linking_page_org: linkingPageOrg || '',
      previous_page: previousPage || '',
      previous_page_org: previousPageOrg || '',
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

    // 2) Context params.
    appendIfValue(sp, 'linking_page', ctx.linking_page);
    appendIfValue(sp, 'linking_page_org', ctx.linking_page_org);
    appendIfValue(sp, 'previous_page', ctx.previous_page);
    appendIfValue(sp, 'previous_page_org', ctx.previous_page_org);
    appendIfValue(sp, 'form_page', ctx.form_page);
    appendIfValue(sp, 'form_page_org', ctx.form_page_org);

    return sp;
  }

  Drupal.behaviors.massFormContextGravityFormsIframe = {
    attach: function (context) {
      var iframes = once('mass-form-context-gf-iframe', 'iframe.js-gf-iframe[data-src]', context);
      if (!iframes.length) {
        return;
      }

      var ignoredKeys = getIgnoredKeys();
      var storage = loadStorage();

      // Snapshot the “two pages before form” BEFORE we change current/prior.
      // These come from page_context.js which runs on non-form pages.
      var linkingPage = storage.current_page;
      var linkingPageOrg = storage.current_page_org;
      var previousPage = storage.prior_page;
      var previousPageOrg = storage.prior_page_org;

      // Always compute the form page (clean URL + org).
      var formPage = getCleanCurrentUrl();
      var formPageOrg = getOrgFromMeta();

      // 1) Capture query params present on the FORM page URL (external → form),
      // except analytics keys. (No URL cleanup.)
      captureQueryParamsIntoStorage(storage, ignoredKeys);

      // 2) Update storage so current_page becomes the FORM page itself,
      // but DO NOT rotate on refresh (same form page).
      if ((storage.current_page || storage.current_page_org) && storage.current_page !== formPage) {
        storage.prior_page = storage.current_page || null;
        storage.prior_page_org = storage.current_page_org || null;
      }
      storage.current_page = formPage;
      storage.current_page_org = formPageOrg;

      // 3) Build and store a debug context object that matches what we’ll send to iframe.
      storage.last_iframe_context = buildIframeContext(
        storage,
        linkingPage,
        linkingPageOrg,
        previousPage,
        previousPageOrg,
        formPage,
        formPageOrg
      );

      saveStorage(storage);

      // 4) Build final params FROM storage.last_iframe_context (single source of truth).
      var finalParams = buildFinalParamsFromContext(storage.last_iframe_context || {qs: {}});

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
