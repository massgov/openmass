(function (Drupal, drupalSettings, once) {
  'use strict';

  const STORAGE_KEY = 'massFormContext';

  function loadStorage() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) {
        return { forms: {} };
      }
      const data = JSON.parse(raw);
      if (!data || typeof data !== 'object') {
        return { forms: {} };
      }
      if (!data.forms || typeof data.forms !== 'object') {
        data.forms = {};
      }
      return data;
    } catch (e) {
      return { forms: {} };
    }
  }

  function saveStorage(storage) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(storage));
    } catch (e) {
      // ignore storage errors (quota, privacy settings, etc.)
    }
  }

  function cleanUrlRemovingAllowed(allowed) {
    if (!allowed.length) return;

    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);
    let changed = false;

    allowed.forEach((key) => {
      if (params.has(key)) {
        params.delete(key);
        changed = true;
      }
    });

    if (!changed) return;

    const newSearch = params.toString();
    const cleanUrl =
      url.origin +
      url.pathname +
      (newSearch ? '?' + newSearch : '') +
      url.hash;

    window.history.replaceState({}, '', cleanUrl);
  }

  Drupal.behaviors.massFormContextForwardQueryToFormLinks = {
    attach(context) {
      const settings =
        drupalSettings.massFormContext?.forwardQueryToFormLinks || {};
      if (!settings.enabled) return;

      const allowed =
        settings.allowedKeys || ['referrer', 'org', 'parentorg', 'site'];

      // 1️⃣ Extract allowed params from current URL.
      const current = new URLSearchParams(window.location.search);
      const filtered = new URLSearchParams();

      current.forEach((value, key) => {
        if (!allowed.length || allowed.includes(key)) {
          filtered.set(key, value);
        }
      });

      if (!filtered.toString()) {
        // Nothing to store for this page.
        return;
      }

      const now = Date.now();
      const storage = loadStorage();

      // 2️⃣ Find all form links on this Info page and map them to this context.
      const selector = [
        'a[href^="/forms/"]',
        'a[href^="https://www.mass.gov/forms/"]',
        'a[href^="https://mass.gov/forms/"]',
      ].join(', ');

      const links = once(
        'mass-form-context-map-forms',
        selector,
        context
      );

      if (links.length) {
        const paramsString = filtered.toString();

        links.forEach((link) => {
          try {
            const url = new URL(link.href, window.location.origin);
            const formPath = url.pathname; // e.g. "/forms/foo"

            storage.forms[formPath] = {
              params: paramsString,
              timestamp: now,
            };
          } catch (e) {
            // ignore invalid links
          }
        });

        // Save back to storage.
        saveStorage(storage);
      }

      // 3️⃣ Clean allowed params from current Info URL, keep the rest (analytics, etc.).
      cleanUrlRemovingAllowed(allowed);
    },
  };
})(Drupal, drupalSettings, once);
