(function () {
  'use strict';

  /**
   * Injects page context query params into Forms iframe src.
   * - Preserves original iframe src + existing query params.
   * - Adds/updates only known context params.
   * - Uses URL + URLSearchParams (no string concat).
   * - Prevents infinite loops / repeated rewrites.
   */

  /**
   * Session storage keys (new first, legacy fallback second).
   * @type {string[]}
   */
  const STORAGE_KEYS = ['massSessionContext', 'mass_form_context'];

  /**
   * Maps form query params to session context keys (first match wins).
   * @type {Object<string, string[]>}
   */
  const CONTEXT_PARAM_MAP = {
    linking_page: ['current_page'],
    previous_page: ['prior_page', 'previous_page'],
    previous_page_2: ['prior_page_2', 'previous_page_2'],
    linking_page_org: ['current_page_org'],
    linking_page_parent_org: ['current_page_parent_org'],
    previous_page_org: ['prior_page_org', 'previous_page_org'],
    previous_page_2_org: ['prior_page_2_org', 'previous_page_2_org'],
    previous_page_parent_org: ['prior_page_parent_org', 'previous_page_parent_org'],
    previous_page_2_parent_org: ['prior_page_2_parent_org', 'previous_page_2_parent_org']
  };

  /**
   * Params we own / should never be overwritten by accumulated journey params.
   * @type {Set<string>}
   */
  const RESERVED_PARAMS = new Set([
    'linking_page',
    'previous_page',
    'previous_page_2',
    'linking_page_org',
    'linking_page_parent_org',
    'previous_page_org',
    'previous_page_2_org',
    'previous_page_parent_org',
    'previous_page_2_parent_org'
  ]);

  // Markers to avoid rewriting the same iframe repeatedly.
  const DATA_APPLIED_ATTR = 'data-mass-form-context-applied';
  const DATA_ORIGINAL_SRC_ATTR = 'data-mass-form-original-src';

  /**
   * Read the stored context object from sessionStorage.
   *
   * @return {Object|null}
   *   Parsed context object or null.
   */
  function readContext() {
    for (let i = 0; i < STORAGE_KEYS.length; i++) {
      const key = STORAGE_KEYS[i];
      try {
        const raw = window.sessionStorage.getItem(key);
        if (!raw) {
          continue;
        }
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object') {
          return parsed;
        }
      }
      catch (e) {
        // ignore and keep trying other keys
      }
    }

    return null;
  }

  /**
   * Normalize values for URLSearchParams.
   *
   * @param {*} val
   *   Value to normalize.
   *
   * @return {string|null}
   *   Normalized string or null.
   */
  function normalizeValue(val) {
    if (typeof val === 'undefined' || val === null) {
      return null;
    }

    if (Array.isArray(val)) {
      const cleaned = val
        .map(function (v) {
          return (v === null || typeof v === 'undefined') ? '' : String(v).trim();
        })
        .filter(function (v) {
          return Boolean(v);
        });
      return cleaned.length ? cleaned.join(',') : null;
    }

    if (typeof val === 'object') {
      // If you store orgs as arrays/strings, this won’t run.
      // If it does, we serialize so it’s still deterministic.
      try {
        const s = JSON.stringify(val);
        return s && s !== '{}' ? s : null;
      }
      catch (e) {
        return null;
      }
    }

    const s = String(val).trim();
    return s ? s : null;
  }

  /**
   * Checks if a URL points at forms.mass.gov.
   *
   * @param {string} urlString
   *   A URL string.
   *
   * @return {boolean}
   *   TRUE if forms URL.
   */
  function isFormsUrl(urlString) {
    if (!urlString) {
      return false;
    }

    let url;
    try {
      url = new URL(urlString, window.location.href);
    }
    catch (e) {
      return false;
    }

    return url.hostname === 'forms.mass.gov';
  }

  /**
   * Returns the canonical base URL for an iframe (prefers data-src).
   *
   * @param {HTMLIFrameElement} iframe
   *   Iframe element.
   *
   * @return {string|null}
   *   Base URL or null.
   */
  function getIframeBaseUrl(iframe) {
    const dataSrc = iframe.getAttribute('data-src');
    if (dataSrc && dataSrc.trim()) {
      return dataSrc.trim();
    }

    const src = iframe.getAttribute('src');
    if (src && src.trim() && src.trim() !== 'about:blank') {
      return src.trim();
    }

    return null;
  }

  /**
   * Sets iframe src and removes data-src (we only want one source of truth).
   *
   * @param {HTMLIFrameElement} iframe
   *   Iframe element.
   * @param {string} urlString
   *   Final URL.
   *
   * @return {void}
   */
  function setIframeUrl(iframe, urlString) {
    iframe.setAttribute('src', urlString);

    if (iframe.hasAttribute('data-src')) {
      iframe.removeAttribute('data-src');
    }
  }

  /**
   * Gets the first non-empty value from a list of keys.
   *
   * @param {Object|null} context
   *   Session context.
   * @param {string[]} keys
   *   Keys to check.
   *
   * @return {string|null}
   *   Normalized value.
   */
  function getFirstContextValue(context, keys) {
    for (let i = 0; i < keys.length; i++) {
      const k = keys[i];
      const raw = (context && Object.prototype.hasOwnProperty.call(context, k)) ? context[k] : null;
      const v = normalizeValue(raw);
      if (v) {
        return v;
      }
    }

    return null;
  }

  /**
   * Apply context to a single iframe element.
   *
   * @param {HTMLIFrameElement} iframe
   *   Iframe element.
   * @param {Object|null} context
   *   Session context.
   *
   * @return {void}
   */
  function applyContextToIframe(iframe, context) {
    if (!iframe || iframe.nodeName !== 'IFRAME') {
      return;
    }

    const base = getIframeBaseUrl(iframe);
    if (!base) {
      return;
    }

    if (!isFormsUrl(base)) {
      return;
    }

    if (iframe.getAttribute(DATA_APPLIED_ATTR) === '1') {
      return;
    }

    let url;
    try {
      url = new URL(base, window.location.href);
    }
    catch (e) {
      return;
    }

    if (!iframe.hasAttribute(DATA_ORIGINAL_SRC_ATTR)) {
      iframe.setAttribute(DATA_ORIGINAL_SRC_ATTR, url.toString());
    }

    let changed = false;

    Object.keys(CONTEXT_PARAM_MAP).forEach(function (paramName) {
      const contextKeys = CONTEXT_PARAM_MAP[paramName];
      const normalized = getFirstContextValue(context, contextKeys);

      if (normalized) {
        const existing = url.searchParams.get(paramName);
        if (existing !== normalized) {
          url.searchParams.set(paramName, normalized);
          changed = true;
        }
      }
    });

    // Append accumulated journey query params (original functionality).
    // Do not override existing iframe params and never override reserved ones.
    if (context && context.qs && typeof context.qs === 'object') {
      Object.keys(context.qs).forEach(function (k) {
        const key = normalizeValue(k);
        const val = normalizeValue(context.qs[k]);
        if (!key || !val) {
          return;
        }
        if (RESERVED_PARAMS.has(key)) {
          return;
        }

        if (!url.searchParams.has(key)) {
          url.searchParams.set(key, val);
          changed = true;
        }
      });
    }

    iframe.setAttribute(DATA_APPLIED_ATTR, '1');

    if (changed) {
      setIframeUrl(iframe, url.toString());
    }
    else {
      const currentSrc = String(iframe.getAttribute('src') || '').trim();
      if (!currentSrc || currentSrc === 'about:blank') {
        setIframeUrl(iframe, url.toString());
      }
    }
  }

  /**
   * Find candidate iframes.
   *
   * @param {ParentNode} root
   *   Root element to query.
   *
   * @return {HTMLIFrameElement[]}
   *   List of iframes.
   */
  function findFormsIframes(root) {
    const scope = root || document;
    return Array.prototype.slice.call(scope.querySelectorAll('iframe[data-src], iframe[src]'));
  }

  /**
   * Bootstrap logic.
   *
   * @return {void}
   */
  function initMassFormIframeContext() {
    const context = readContext();

    function applyAll() {
      const ctx = readContext() || context;
      findFormsIframes(document).forEach(function (iframe) {
        applyContextToIframe(iframe, ctx);
      });
    }

    applyAll();

    const observer = new MutationObserver(function (mutations) {
      const ctx = readContext() || context;

      for (let i = 0; i < mutations.length; i++) {
        const m = mutations[i];

        if (m.type === 'childList') {
          m.addedNodes.forEach(function (node) {
            if (node.nodeType !== 1) {
              // ELEMENT_NODE
              return;
            }

            if (node.nodeName === 'IFRAME') {
              applyContextToIframe(node, ctx);
            }
            else {
              findFormsIframes(node).forEach(function (iframe) {
                applyContextToIframe(iframe, ctx);
              });
            }
          });
        }

        if (m.type === 'attributes') {
          const el = m.target;
          if (el && el.nodeName === 'IFRAME' && (m.attributeName === 'src' || m.attributeName === 'data-src')) {
            el.removeAttribute(DATA_APPLIED_ATTR);
            applyContextToIframe(el, ctx);
          }
        }
      }
    });

    observer.observe(document.documentElement, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['src', 'data-src']
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMassFormIframeContext);
  }
  else {
    initMassFormIframeContext();
  }
})();
