/**
 * @file
 * Message box dialog Save/Cancel inside the Ajax modal (Drupal behavior).
 *
 * Pairs with dialog-open.js in the CKEditor plugin. That file opens the
 * dialog; this file makes Save use Ajax and blocks nested Message box buttons
 * in the body field editor.
 */
(function ($, Drupal, drupalSettings) {
  'use strict';

  var handlersBound = false;
  var DIALOG_ROUTE_FRAGMENT = '/mass-inline-message/dialog/';

  function ensureMassInlineMessageModalContainer() {
    if (document.getElementById('mass-inline-message-modal')) {
      return;
    }
    var container = document.createElement('div');
    container.id = 'mass-inline-message-modal';
    container.className = 'ui-front';
    container.style.display = 'none';
    document.body.appendChild(container);
  }

  function getAjaxInstance(button) {
    var matched = null;
    (Drupal.ajax.instances || []).some(function (instance) {
      if (instance && instance.element === button) {
        matched = instance;
        return true;
      }
      return false;
    });
    return matched;
  }

  function syncDialogBodyEditors(form) {
    if (!form || !Drupal.CKEditor5Instances) {
      return;
    }
    form.querySelectorAll('[data-ckeditor5-id]').forEach(function (textarea) {
      var editor = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
      if (editor) {
        editor.updateSourceElement();
      }
    });
  }

  function disableNestedMessageBoxInDialog(form) {
    if (!form || !Drupal.CKEditor5Instances) {
      return;
    }
    form.querySelectorAll('textarea[data-ckeditor5-id]').forEach(function (textarea) {
      var editorId = textarea.getAttribute('data-ckeditor5-id');
      var editor = Drupal.CKEditor5Instances.get(editorId);
      if (!editor) {
        return;
      }

      var command = editor.commands && editor.commands.get
        ? editor.commands.get('insertMassInlineMessage')
        : null;
      if (command && command.forceDisabled) {
        command.forceDisabled('massInlineMessageDialog');
      }

      var toolbarButtons = form.querySelectorAll('.ck-toolbar .ck-button');
      toolbarButtons.forEach(function (button) {
        var label = (
          button.getAttribute('aria-label') ||
          button.getAttribute('data-cke-tooltip-text') ||
          button.getAttribute('title') ||
          button.textContent ||
          ''
        ).toLowerCase().trim();
        if (label.indexOf('message box') !== -1) {
          button.style.display = 'none';
          button.setAttribute('aria-hidden', 'true');
          button.setAttribute('tabindex', '-1');
        }
      });
    });
  }

  function scheduleDialogEditorGuards(form, attempts) {
    if (!form || attempts <= 0) {
      return;
    }
    disableNestedMessageBoxInDialog(form);
    window.setTimeout(function () {
      scheduleDialogEditorGuards(form, attempts - 1);
    }, 150);
  }

  function setFormInFlightState($form, isInFlight) {
    $form.data('massInlineMessageAjaxInFlight', isInFlight);
    $form.find('input[type="submit"], button[type="submit"]').prop('disabled', !!isInFlight);
  }

  function releaseInFlightWhenDialogAjaxFinishes($form) {
    var matcher = function (event, xhr, settings) {
      var url = (settings && settings.url) || '';
      if (url.indexOf(DIALOG_ROUTE_FRAGMENT) === -1) {
        return;
      }
      $(document).off('ajaxComplete.massInlineMessageDialog ajaxError.massInlineMessageDialog', matcher);
      if ($form && $form.length) {
        setFormInFlightState($form, false);
      }
    };
    $(document).on('ajaxComplete.massInlineMessageDialog ajaxError.massInlineMessageDialog', matcher);
  }

  function submitDialogButtonViaAjax($button) {
    var button = $button.get(0);
    var $form = $button.closest('form.mass-inline-message-dialog-form');
    if (!button || !$form.length) {
      return false;
    }
    if ($form.data('massInlineMessageAjaxInFlight')) {
      return true;
    }
    setFormInFlightState($form, true);
    releaseInFlightWhenDialogAjaxFinishes($form);

    syncDialogBodyEditors($form.get(0));

    var ajaxInstance = getAjaxInstance(button);
    if (ajaxInstance) {
      ajaxInstance.eventResponse(button, $.Event(ajaxInstance.event || 'click'));
      return true;
    }

    if (!button.id) {
      button.id = 'mass-inline-message-dialog-save-' + Date.now();
    }
    var ajax = Drupal.ajax({
      base: button.id,
      element: button,
      url: $form.attr('action') || window.location.href,
      event: 'click',
      setClick: true,
      progress: {type: 'throbber'}
    });
    ajax.eventResponse(button, $.Event('click'));
    return true;
  }

  function isMessageBoxDialog($dialog) {
    return $dialog.length && $dialog.find('#mass-inline-message-dialog-form').length > 0;
  }

  /**
   * Returns the Message box dialog only when the event target is inside it.
   *
   * Do not fall back to "any visible Message box dialog" — stacked modals
   * (entity embed, file browser, etc.) must handle their own button pane clicks.
   */
  function getMessageBoxDialogFromElement(element) {
    var $dialog = $(element).closest('.ui-dialog');
    return isMessageBoxDialog($dialog) ? $dialog : $();
  }

  function findSaveSubmitter($form, target, fromButtonPane) {
    var opLabel = '';
    if (fromButtonPane) {
      var $visible = $(target).closest('.ui-dialog-buttonpane button, .ui-dialog-buttonpane .button');
      opLabel = ($visible.text() || $visible.val() || '').trim();
    }

    var $submitters = $form.find(
      '.form-actions input[type="submit"]:not(.dialog-cancel), .form-actions button[type="submit"]:not(.dialog-cancel)'
    );

    if (opLabel) {
      var $match = $submitters.filter(function () {
        return ($(this).val() || $(this).text() || '').trim() === opLabel;
      }).first();
      if ($match.length) {
        return $match;
      }
    }

    var $primary = $submitters.filter('.button--primary').first();
    if ($primary.length) {
      return $primary;
    }

    return $submitters.first();
  }

  function isMessageBoxToolbarButton(target) {
    if (!(target instanceof Element)) {
      return false;
    }
    var button = target.closest('.ck-toolbar .ck-button');
    if (!button) {
      return false;
    }
    var label = (
      button.getAttribute('aria-label') ||
      button.getAttribute('data-cke-tooltip-text') ||
      button.getAttribute('title') ||
      button.textContent ||
      ''
    ).toLowerCase().trim();
    return label.indexOf('message box') !== -1;
  }

  function bindGlobalHandlers() {
    if (handlersBound) {
      return;
    }
    handlersBound = true;

    document.addEventListener('click', function (event) {
      var target = event.target;
      if (!(target instanceof Element)) {
        return;
      }

      var $dialog = getMessageBoxDialogFromElement(target);
      if (!$dialog.length) {
        return;
      }

      var $form = $dialog.find('form.mass-inline-message-dialog-form');
      if (!$form.length) {
        return;
      }

      // Never allow nested Message box insertion inside the dialog body editor.
      if (target.closest('form.mass-inline-message-dialog-form') && isMessageBoxToolbarButton(target)) {
        event.preventDefault();
        event.stopImmediatePropagation();
        return;
      }

      if (target.closest('.dialog-cancel')) {
        return;
      }

      var fromButtonPane = !!target.closest('.ui-dialog-buttonpane');
      var fromFormActions = !!target.closest('form.mass-inline-message-dialog-form .form-actions');

      if (!fromButtonPane && !fromFormActions) {
        return;
      }

      if (target.matches('input[type="submit"], button[type="submit"], .ui-dialog-buttonpane button')) {
        event.preventDefault();
        event.stopImmediatePropagation();

        var $submitter = fromFormActions && target.closest('.form-actions')
          ? $(target).closest('input[type="submit"], button[type="submit"]')
          : findSaveSubmitter($form, target, fromButtonPane);

        if ($submitter.length) {
          submitDialogButtonViaAjax($submitter);
        }
      }
    }, true);

    document.addEventListener('submit', function (event) {
      var form = event.target;
      if (!(form instanceof HTMLFormElement) || !form.classList.contains('mass-inline-message-dialog-form')) {
        return;
      }

      var $form = $(form);
      if ($form.data('massInlineMessageAjaxInFlight')) {
        return;
      }

      var submitter = event.submitter;
      if (submitter && submitter.classList.contains('dialog-cancel')) {
        return;
      }

      event.preventDefault();
      event.stopImmediatePropagation();

      var $submitter = submitter ? $(submitter) : findSaveSubmitter($form, form, false);
      if ($submitter.length) {
        submitDialogButtonViaAjax($submitter);
      }
    }, true);
  }

  function wireDialogContent(dialogContent) {
    if (!dialogContent || !dialogContent.querySelector) {
      return;
    }
    if (!dialogContent.querySelector('#mass-inline-message-dialog-form')) {
      return;
    }

    var dialogWrapper = dialogContent.closest('.ui-dialog');
    if (dialogWrapper) {
      dialogWrapper.classList.add('mass-inline-message-dialog');
    }

    var form = dialogContent.querySelector('form.mass-inline-message-dialog-form');
    if (form) {
      Drupal.attachBehaviors(form, drupalSettings);
      scheduleDialogEditorGuards(form, 12);
    }
  }

  Drupal.behaviors.massInlineMessageDialog = {
    attach: function (context) {
      ensureMassInlineMessageModalContainer();
      bindGlobalHandlers();

      $(context).find('form.mass-inline-message-dialog-form').each(function () {
        scheduleDialogEditorGuards(this, 4);
      });

      if (context instanceof Element || context === document) {
        var root = context === document ? document : context;
        root.querySelectorAll('.ui-dialog-content').forEach(function (dialogContent) {
          wireDialogContent(dialogContent);
        });
      }
    }
  };

  window.addEventListener('dialog:aftercreate', function (event) {
    if (event.target) {
      wireDialogContent(event.target);
    }
  });

  window.addEventListener('dialogButtonsChange', function (event) {
    if (event.target) {
      wireDialogContent(event.target);
    }
  });

})(jQuery, Drupal, drupalSettings);
