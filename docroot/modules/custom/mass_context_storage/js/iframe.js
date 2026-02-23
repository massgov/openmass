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
   * Local storage key (single source of truth).
   * @type {string[]}
   */
  const STORAGE_KEYS = ['massContextStorage'];

  // 1 hour idle TTL (must match page_context.js).
  const TTL_MS = 60 * 60 * 1000;

  function now() {
    return Date.now ? Date.now() : new Date().getTime();
  }

  function clearStoredValue(storageKey) {
    try {
      window.localStorage.removeItem(storageKey);
    }
    catch (e) {
      // ignore
    }
  }

  /**
   * Context keys we send to the form. These key names are identical in session storage and iframe query params.
   * @type {string[]}
   */
  const CONTEXT_KEYS = [
    'current_page',
    'previous_page',
    'previous_page_2',
    'current_page_org',
    'current_page_parent_org',
    'previous_page_org',
    'previous_page_2_org',
    'previous_page_parent_org',
    'previous_page_2_parent_org'
  ];

  /**
   * Params we own / should never be overwritten by accumulated journey params.
   * @type {Set<string>}
   */
  const RESERVED_PARAMS = new Set(CONTEXT_KEYS);

  // Markers to avoid rewriting the same iframe repeatedly.
  const DATA_APPLIED_ATTR = 'data-mass-form-context-applied';
  const DATA_ORIGINAL_SRC_ATTR = 'data-mass-form-original-src';

  /**
   * Read the stored context object from localStorage.
   *
   * @return {Object|null}
   *   Parsed context object or null.
   */
  function readContext() {
    for (let i = 0; i < STORAGE_KEYS.length; i++) {
      const key = STORAGE_KEYS[i];
      try {
        const raw = window.localStorage.getItem(key);
        if (!raw) {
          continue;
        }
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object') {
          // TTL: if last pageview is older than 1 hour, clear and ignore.
          if (parsed._ts && (now() - parsed._ts) > TTL_MS) {
            clearStoredValue(key);
            continue;
          }
          return parsed;
        }
      }
      catch (e) {
        // ignore and keep trying
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

    let url;
    try {
      url = new URL(base, window.location.href);
    }
    catch (e) {
      return;
    }

    if (url.hostname !== 'forms.mass.gov') {
      return;
    }

    if (iframe.getAttribute(DATA_APPLIED_ATTR) === '1') {
      return;
    }

    if (!iframe.hasAttribute(DATA_ORIGINAL_SRC_ATTR)) {
      iframe.setAttribute(DATA_ORIGINAL_SRC_ATTR, url.toString());
    }

    let changed = false;

    CONTEXT_KEYS.forEach(function (key) {
      const normalized = normalizeValue(context ? context[key] : null);
      if (!normalized) {
        return;
      }

      const existing = url.searchParams.get(key);
      if (existing !== normalized) {
        url.searchParams.set(key, normalized);
        changed = true;
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
