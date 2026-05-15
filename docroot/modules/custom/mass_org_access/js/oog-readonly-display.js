/**
 * Keeps the read-only Owner Groups display in sync with the hidden
 * autocomplete input. After the "Browse organizations" popup updates
 * the input's value, JS re-renders the visible <ul>.
 *
 * @param {Drupal} Drupal Drupal global object providing behaviors registry.
 * @param {Function} once Core/once helper for one-time element processing.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.massOrgAccessOogDisplay = {
    attach: function (context) {
      const sources = once(
        'oog-readonly-display',
        '.oog-readonly-source-hidden input.form-autocomplete[data-drupal-selector*="content-organization"]',
        context
      );
      sources.forEach(function (input) {
        const wrapper = input.closest('.field--name-field-content-organization');
        if (!wrapper) {
          return;
        }
        const display = wrapper.querySelector('.oog-readonly-display');
        if (!display) {
          return;
        }

        const render = function () {
          const labels = parseAutocomplete(input.value);
          if (labels.length) {
            display.innerHTML =
              '<ul>' +
              labels.map(function (label) {
                return '<li>' + escapeHtml(label) + '</li>';
              }).join('') +
              '</ul>';
          }
          else {
            display.innerHTML = '<em>(none assigned)</em>';
          }
        };

        // Native events on the input (typed/cleared programmatically).
        input.addEventListener('change', render);
        input.addEventListener('input', render);

        // entity_reference_tree closes its popup by writing to the input
        // via jQuery .val() without dispatching events, so observe the
        // attribute and also poll as a fallback.
        new MutationObserver(render).observe(input, {
          attributes: true,
          attributeFilter: ['value']
        });
        let last = input.value;
        setInterval(function () {
          if (input.value !== last) {
            last = input.value;
            render();
          }
        }, 500);
      });
    }
  };

  /**
   * Parses the entity_autocomplete tag format into an array of labels.
   *
   * Items are separated by ", "; values that contain ", " or quote
   * characters are wrapped in double quotes by Drupal. Each item also
   * carries a trailing " (TID)" that we strip for display.
   *
   * @param {string} value Raw value from the autocomplete input.
   *
   * @return {string[]} Array of cleaned-up term labels.
   */
  function parseAutocomplete(value) {
    if (!value) {
      return [];
    }
    const items = [];
    let current = '';
    let inQuotes = false;
    for (let i = 0; i < value.length; i++) {
      const c = value[i];
      if (c === '"') {
        inQuotes = !inQuotes;
        continue;
      }
      if (c === ',' && !inQuotes && value[i + 1] === ' ') {
        items.push(current);
        current = '';
        i++;
        continue;
      }
      current += c;
    }
    if (current.trim()) {
      items.push(current);
    }
    return items
      .map(function (s) {
        s = s.trim();
        if (s.startsWith('"') && s.endsWith('"')) {
          s = s.slice(1, -1);
        }
        return s.replace(/\s*\(\d+\)\s*$/, '');
      })
      .filter(Boolean);
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
})(Drupal, once);
