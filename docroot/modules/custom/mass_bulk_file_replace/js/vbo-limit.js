(function (Drupal, drupalSettings, once) {
  'use strict';
  Drupal.behaviors.massVboLimit = {
    attach: function (context) {
      const cfg = (drupalSettings.massBulkFileReplace || {}).vboLimit;
      if (!cfg || !cfg.limit) {
        return;
      }

      const limit = parseInt(cfg.limit, 10);
      const checkboxSelector = cfg.checkboxSelector || '.views-table tbody input.js-vbo-checkbox';
      const footerSelector = cfg.footerSelector || '.vbo-view-form .vbo-operations';

      const boxes = once('massVboLimit', checkboxSelector, context);
      if (!boxes.length) {
        return;
      }

      const headerBoxList = once('massVboLimitHeader', '.views-table thead .select-all input[type="checkbox"]', context);
      const headerBox = headerBoxList.length ? headerBoxList[0] : null;

      function parseCount(el) {
        if (!el) {
          return 0;
        }
        const m = (el.textContent || '').match(/\b(\d+)\b/);
        return m ? parseInt(m[1], 10) : 0;
      }

      // Total selected: prefer multipage summary when present; fallback to live page status.
      function getSelectedTotal() {
        // VBO multipage summary often reflects the full aggregate (including current page).
        const mpSummary = document.querySelector('#edit-multipage summary');
        const multi = parseCount(mpSummary);
        if (multi > 0) {
          return multi;
        }
        // Fallback: on-page live status elements like #edit-status or #edit-status--N
        let live = 0;
        document.querySelectorAll('[id^="edit-status"]').forEach(el => {
          live = Math.max(live, parseCount(el));
        });
        return live;
      }

      let msgEl = null;
      function showMessage(text) {
        const footer = document.querySelector(footerSelector);
        if (!footer) {
          return;
        }
        if (!msgEl) {
          msgEl = document.createElement('div');
          msgEl.setAttribute('data-vbo-limit-msg', '1');
          msgEl.style.marginTop = '8px';
          footer.appendChild(msgEl);
        }
        msgEl.textContent = text;
      }
      function clearMessage() {
        if (msgEl) {
          msgEl.remove();
          msgEl = null;
        }
      }

      function pageAllChecked() {
        return boxes.every(cb => cb.checked);
      }

      function enforceCap() {
        const total = getSelectedTotal();
        const remaining = Math.max(0, limit - total);
        const unselectedOnPageCount = boxes.filter(cb => !cb.checked).length;

        // Disable remaining row checkboxes only when the cap is fully reached; re-enable otherwise.
        boxes.forEach((cb) => {
          if (!cb.checked) {
            if (remaining === 0) {
              cb.disabled = true;
              cb.setAttribute('disabled', 'disabled');
              cb.setAttribute('aria-disabled', 'true');
            }
            else {
              cb.disabled = false;
              cb.removeAttribute('disabled');
              cb.removeAttribute('aria-disabled');
            }
          }
        });

        // Disable header if clicking it could exceed the limit, or when at cap.
        if (headerBox) {
          const safeToSelectAll = remaining >= unselectedOnPageCount && unselectedOnPageCount > 0;
          const atCap = remaining === 0;
          headerBox.disabled = atCap || !safeToSelectAll;
          headerBox.checked = !headerBox.disabled && pageAllChecked();
        }

        // Optional UX: message when fully capped.
        if (remaining === 0) {
          showMessage('Selection limit reached (' + limit + '). Deselect an item to pick another.');
        }
        else {
          clearMessage();
        }
      }

      // Re-enforce whenever a row checkbox changes (VBO updates counters afterward)
      boxes.forEach((cb) => {
        cb.addEventListener('change', function () {
          setTimeout(enforceCap, 0);
        }, {passive: true, once: false});
      });

      // Watch the multipage counter and on-page status for text changes
      const mp = document.querySelector('#edit-multipage summary');
      const watchTargets = [];
      if (mp) {
        watchTargets.push(mp);
      }
      document.querySelectorAll('[id^="edit-status"]').forEach(el => watchTargets.push(el));
      if (watchTargets.length) {
        const mo = new MutationObserver(() => enforceCap());
        watchTargets.forEach(t => mo.observe(t, {childList: true, subtree: true, characterData: true}));
      }

      // Initial enforcement
      enforceCap();
    }
  };
})(Drupal, drupalSettings, once);
