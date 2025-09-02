(function (Drupal, once) {
  'use strict';

  const MEMO_KEY = 'scrollMemo';
  const FOCUS_KEY = 'lastFocus';

  function isInDialog(el) {
    if (!el || !el.closest) { return false; }
    return !!(el.closest('.ui-dialog .ui-dialog-content') || el.closest('#drupal-off-canvas'));
  }

  // Return the topmost visible dialog-like scroller (modal or off-canvas).
  function getTopDialogContent() {
    if (window.jQuery) {
      const $ = window.jQuery;
      // Prefer currently visible jQuery UI dialogs.
      const $dlg = $('.ui-dialog:visible .ui-dialog-content:visible').last();
      if ($dlg.length) { return $dlg.get(0); }
    }
    // Fallbacks: off-canvas in Drupal and any open dialog content.
    const offCanvas = document.getElementById('drupal-off-canvas');
    if (offCanvas && offCanvas.offsetParent !== null) { return offCanvas; }

    const dialogs = Array.from(document.querySelectorAll('.ui-dialog .ui-dialog-content'));
    return dialogs.length ? dialogs[dialogs.length - 1] : null;
  }

  function getScrollTop(dialogContent) {
    return dialogContent ? dialogContent.scrollTop : 0;
  }

  function setScrollTop(dialogContent, top) {
    if (!dialogContent) { return; }
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        dialogContent.scrollTop = top;
      });
    });
  }

  function focusNoScroll(el) {
    if (!el || typeof el.focus !== 'function') { return false; }
    try {
      el.focus({preventScroll: true});
      return true;
    }
    catch (err) {
      const pos = {scrollX: window.scrollX, scrollY: window.scrollY};
      el.focus();
      window.scrollTo(pos.scrollX, pos.scrollY);
      return true;
    }
  }

  function rememberFocus(dialogContent, target) {
    if (!dialogContent) { return; }
    // Prefer stable selectors; fall back to element reference.
    let selector = null;
    const id = target.id && document.getElementById(target.id) === target ? ('#' + CSS.escape(target.id)) : null;
    if (id) {
      selector = id;
    }
    else if (target.name) {
      // Best-effort within this dialog only.
      const nameSel = '[name="' + CSS.escape(target.name) + '"]';
      if (dialogContent.querySelector(nameSel)) { selector = nameSel; }
    }
    dialogContent[FOCUS_KEY] = selector || target; // store selector or node
  }

  function resolveRememberedFocus(dialogContent) {
    const saved = dialogContent && dialogContent[FOCUS_KEY];
    if (!saved) { return null; }
    if (typeof saved === 'string') { return dialogContent.querySelector(saved); }
    if (saved instanceof Element && dialogContent.contains(saved)) { return saved; }
    return null;
  }

  function stripAutofocus(dialogContent) {
    dialogContent.querySelectorAll('[autofocus]').forEach(function (el) { el.removeAttribute('autofocus'); });
  }

  function wireDialog(dialogContent) {
    // Track last focused control inside this dialog.
    dialogContent.addEventListener(
      'focusin',
      function (e) {
        const t = e.target;
        if (!(t instanceof HTMLElement)) { return; }
        // Skip CKEditor internals/shadow hosts.
        if (t.closest('.ck,.ck-reset_all')) { return; }
        rememberFocus(dialogContent, t);
      },
      true
    );

    // BEFORE AJAX triggered from inside this dialog: store scroll + last focus.
    const beforeAjax = function (targetEl) {
      if (!targetEl || !isInDialog(targetEl)) { return; }
      dialogContent[MEMO_KEY] = getScrollTop(dialogContent);
      // If nothing focused yet, try the activeElement.
      if (!dialogContent[FOCUS_KEY] && dialogContent.contains(document.activeElement)) {
        rememberFocus(dialogContent, document.activeElement);
      }
    };

    // AFTER AJAX completes for a request originating in this dialog:
    // 1) remove any brand-new autofocus
    // 2) restore scroll
    // 3) refocus the previous field with preventScroll
    const afterAjax = function (targetEl) {
      if (!targetEl || !isInDialog(targetEl)) { return; }

      // Give DOM time to settle.
      requestAnimationFrame(function () {
        stripAutofocus(dialogContent);

        const top = parseInt(dialogContent[MEMO_KEY] || '0', 10);
        setScrollTop(dialogContent, top);

        // Try to focus the remembered field; fallback to first invalid or the submit button.
        var focusTarget = resolveRememberedFocus(dialogContent)
          || dialogContent.querySelector('.error, [aria-invalid=\'true\'], :invalid')
          || dialogContent.querySelector('input, select, textarea, button');

        if (focusTarget) {
          focusNoScroll(focusTarget);
        }
      });
    };

    // — Hook into Drupal AJAX (jQuery-based) —
    // Patch Drupal.AjaxCommands.insert to preserve scroll & focus across DOM replacements.
    (function patchAjaxInsertOnce() {
      if (!Drupal.AjaxCommands || patchAjaxInsertOnce._done) { return; }
      const proto = Drupal.AjaxCommands.prototype;
      if (!proto || !proto.insert) { return; }
      const originalInsert = proto.insert;
      proto.insert = function (ajax, response, status) {
        // Memo: current dialog and its scroll + last focused element.
        const dlgContent = getTopDialogContent();
        const scroller = dlgContent || document.scrollingElement || document.documentElement;
        const memoTop = scroller ? scroller.scrollTop : 0;
        const active = document.activeElement && isInDialog(document.activeElement)
          ? document.activeElement
          : null;

        // Run core behavior.
        originalInsert.call(this, ajax, response, status);

        // After DOM changes settle, strip autofocus, restore scroll and refocus.
        requestAnimationFrame(function () {
          const dc = getTopDialogContent() || dlgContent;
          if (dc) {
            stripAutofocus(dc);
            // Two RAFs to ensure layout is committed.
            requestAnimationFrame(function () {
              if (typeof memoTop === 'number') {
                dc.scrollTop = memoTop;
              }
              const remembered = resolveRememberedFocus(dc) || active;
              if (remembered) {
                focusNoScroll(remembered);
              }
            });
          }
        });
      };
      patchAjaxInsertOnce._done = true;
    })();

    if (window.jQuery) {
      const $ = window.jQuery;
      $(document)
        .on('ajaxSend.modalScrollGuard', function (evt, xhr, settings) {
          // settings.extraData?._triggering_element_name is often present
          // but we’ll look for the currently active/trigger element in the dialog.
          const active = document.activeElement;
          const trigger = (active && isInDialog(active)) ? active : dialogContent;
          beforeAjax(trigger);
        })
        .on('ajaxComplete.modalScrollGuard', function (evt, xhr, settings) {
          const active = document.activeElement;
          const trigger = (active && isInDialog(active)) ? active : dialogContent;
          afterAjax(trigger);
        });
    }

    // — Safety net: MutationObserver for non-Drupal AJAX reflows —
    // When subtree changes a lot (form re-render), reapply the memo.
    const mo = new MutationObserver(function (list) {
      // Only react if a large re-render happened (childList changes).
      const bigChange = list.some(function (m) { return m.type === 'childList' && (m.addedNodes.length || m.removedNodes.length); });
      if (!bigChange) { return; }
      // If the dialog recently stored a scroll memo, restore it again.
      if (dialogContent[MEMO_KEY] != null) {
        requestAnimationFrame(function () {
          stripAutofocus(dialogContent);
          setScrollTop(dialogContent, parseInt(dialogContent[MEMO_KEY] || '0', 10));
          const t = resolveRememberedFocus(dialogContent);
          if (t) { focusNoScroll(t); }
        });
      }
    });
    mo.observe(dialogContent, {subtree: true, childList: true});
  }

  Drupal.behaviors.modalScrollGuard = {
    attach: function (context) {
      const dialogs = once(
        'modal-scroll-guard-v2',
        context.querySelectorAll('.ui-dialog .ui-dialog-content, #drupal-off-canvas')
      );
      dialogs.forEach(wireDialog);
    },
    detach: function (context) {
      // Optional: clean jQuery handlers on full detach.
      if (window.jQuery) {
        const $ = window.jQuery;
        $(document).off('.modalScrollGuard');
      }
    }
  };
})(Drupal, once);
