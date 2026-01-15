/**
 * Injects page context query params into Forms iframe src.
 * - Preserves original iframe src + existing query params.
 * - Adds/updates only known context params.
 * - Uses URL + URLSearchParams (no string concat).
 * - Prevents infinite loops / repeated rewrites.
 */

/**
 * Adjust these if your storage key or shape differs.
 */
const STORAGE_KEYS = ['massSessionContext', 'mass_form_context'];

// The query params we support sending to the form.
const CONTEXT_PARAM_MAP = {
  linking_page: ['current_page'],
  previous_page: ['prior_page', 'previous_page'],
  linking_page_org: ['current_page_org'],
  previous_page_org: ['prior_page_org', 'previous_page_org'],
  previous_page_parent_org: ['prior_page_parent_org', 'previous_page_parent_org'],

};

// Params we own / should never be overwritten by journey query params.
const RESERVED_PARAMS = new Set([
  'linking_page',
  'previous_page',
  'linking_page_org',
  'previous_page_org',
  'previous_page_parent_org',
]);

// Markers to avoid rewriting the same iframe repeatedly.
const DATA_APPLIED_ATTR = 'data-mass-form-context-applied';
const DATA_ORIGINAL_SRC_ATTR = 'data-mass-form-original-src';

/**
 * Read the stored context object from sessionStorage.
 */
function readContext() {
  for (const key of STORAGE_KEYS) {
    try {
      const raw = window.sessionStorage.getItem(key);
      if (!raw) continue;
      const parsed = JSON.parse(raw);
      if (parsed && typeof parsed === 'object') {
        return parsed;
      }
    } catch (e) {
      // ignore and keep trying other keys
    }
  }

  return null;
}

/**
 * Normalize values:
 * - empty -> null
 * - arrays -> comma-separated string
 * - objects -> JSON string (but generally try not to send objects)
 */
function normalizeValue(val) {
  if (val === undefined || val === null) return null;

  if (Array.isArray(val)) {
    const cleaned = val
      .map((v) => (v == null ? '' : String(v).trim()))
      .filter(Boolean);
    return cleaned.length ? cleaned.join(',') : null;
  }

  if (typeof val === 'object') {
    // If you store orgs as arrays/strings, this won’t run.
    // If it does, we serialize so it’s still deterministic.
    try {
      const s = JSON.stringify(val);
      return s && s !== '{}' ? s : null;
    } catch (e) {
      return null;
    }
  }

  const s = String(val).trim();
  return s ? s : null;
}

function isFormsUrl(urlString) {
  if (!urlString) return false;

  let url;
  try {
    url = new URL(urlString, window.location.href);
  } catch (e) {
    return false;
  }

  return url.hostname === 'forms.mass.gov';
}

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

function setIframeUrl(iframe, urlString) {
  // Set real src so iframe loads.
  iframe.setAttribute('src', urlString);

  // If data-src exists, remove it after applying.
  if (iframe.hasAttribute('data-src')) {
    iframe.removeAttribute('data-src');
  }
}

function getFirstContextValue(context, keys) {
  for (const k of keys) {
    const v = normalizeValue(context?.[k]);
    if (v) return v;
  }
  return null;
}

/**
 * Apply context to a single iframe element.
 */
function applyContextToIframe(iframe, context) {
  if (!iframe || iframe.nodeName !== 'IFRAME') return;

  const base = getIframeBaseUrl(iframe);
  if (!base) return;

  // Only apply to forms iframes.
  if (!isFormsUrl(base)) return;

  // Prevent repeated rewrites.
  if (iframe.getAttribute(DATA_APPLIED_ATTR) === '1') return;

  let url;
  try {
    url = new URL(base, window.location.href);
  } catch (e) {
    return;
  }

  // Store original base once.
  if (!iframe.hasAttribute(DATA_ORIGINAL_SRC_ATTR)) {
    iframe.setAttribute(DATA_ORIGINAL_SRC_ATTR, url.toString());
  }

  let changed = false;

  Object.entries(CONTEXT_PARAM_MAP).forEach(([paramName, contextKeys]) => {
    const normalized = getFirstContextValue(context, contextKeys);

    // Only set when we have a value; do not delete existing.
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
    Object.entries(context.qs).forEach(([k, v]) => {
      const key = normalizeValue(k);
      const val = normalizeValue(v);
      if (!key || !val) return;
      if (RESERVED_PARAMS.has(key)) return;

      // Do not override existing iframe params.
      if (!url.searchParams.has(key)) {
        url.searchParams.set(key, val);
        changed = true;
      }
    });
  }

  // Mark applied to avoid loops.
  iframe.setAttribute(DATA_APPLIED_ATTR, '1');

  if (changed) {
    setIframeUrl(iframe, url.toString());
  } else {
    // Even if nothing changed, ensure the iframe loads if it is still about:blank.
    const currentSrc = (iframe.getAttribute('src') || '').trim();
    if (!currentSrc || currentSrc === 'about:blank') {
      setIframeUrl(iframe, url.toString());
    }
  }
}

/**
 * Find candidate iframes and apply.
 * Adjust selector if your markup is more specific.
 */
function findFormsIframes(root = document) {
  return Array.from(root.querySelectorAll('iframe[data-src], iframe[src]'));
}

/**
 * Main entry:
 * - Apply once on DOM ready
 * - Also watch for dynamically injected iframes (AJAX, behaviors, etc.)
 */
function initMassFormIframeContext() {
  const context = readContext();

  // If no context, still set up observer (sometimes context is written slightly later).
  function applyAll() {
    const ctx = readContext() || context;
    findFormsIframes().forEach((iframe) => applyContextToIframe(iframe, ctx));
  }

  // Apply immediately (in case script loads late).
  applyAll();

  // MutationObserver for dynamically added iframes or src changes.
  const observer = new MutationObserver((mutations) => {
    // Re-read context each time because page-context.js might update it after load.
    const ctx = readContext() || context;

    for (const m of mutations) {
      if (m.type === 'childList') {
        m.addedNodes.forEach((node) => {
          if (node.nodeType !== 1) return; // ELEMENT_NODE
          if (node.nodeName === 'IFRAME') {
            applyContextToIframe(node, ctx);
          } else {
            // If a container is injected, scan inside it.
            findFormsIframes(node).forEach((iframe) => applyContextToIframe(iframe, ctx));
          }
        });
      }

      if (m.type === 'attributes') {
        const el = m.target;
        if (el && el.nodeName === 'IFRAME' && (m.attributeName === 'src' || m.attributeName === 'data-src')) {
          // If src/data-src changes after we already applied, allow one more apply.
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
    attributeFilter: ['src', 'data-src'],
  });
}

// Bootstrap
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initMassFormIframeContext);
} else {
  initMassFormIframeContext();
}
