/**
 * @file
 * Message box dialog Save/Cancel inside the Ajax modal (Drupal behavior).
 *
 * Pairs with dialog-open.js in the CKEditor plugin. That file opens the
 * dialog; this file makes Save use Ajax inside the modal.
 */
(function ($, Drupal, drupalSettings) {
  'use strict';

  var handlersBound = false;
  var saveListenersBound = false;
  var DIALOG_ROUTE_FRAGMENT = '/mass-inline-message/dialog/';

  /**
   * Whether Ajax values are from the Message box dialog (not entity embed, etc.).
   */
  function isMessageBoxDialogSaveValues(values) {
    if (!values || typeof values !== 'object') {
      return false;
    }
    if (!Object.prototype.hasOwnProperty.call(values, 'body')) {
      return false;
    }
    var attrs = values.attributes;
    if (!attrs || typeof attrs !== 'object') {
      return false;
    }
    if (
      Object.prototype.hasOwnProperty.call(attrs, 'data-entity-type')
      || Object.prototype.hasOwnProperty.call(attrs, 'data-entity-uuid')
    ) {
      return false;
    }
    return Object.prototype.hasOwnProperty.call(attrs, 'data-title')
      && (attrs['data-type'] === 'info' || attrs['data-type'] === 'warning');
  }

  /**
   * Runs the CKEditor save callback once (survives dialog:afterclose races).
   */
  function invokeMessageBoxSaveCallback(values) {
    if (!window.__massInlineMessageSaveCallback) {
      return false;
    }
    if (!isMessageBoxDialogSaveValues(values)) {
      return false;
    }
    var callback = window.__massInlineMessageSaveCallback;
    delete window.__massInlineMessageSaveCallback;
    if (window.Drupal && window.Drupal.ckeditor5) {
      window.Drupal.ckeditor5.saveCallback = null;
    }
    callback(values);
    return true;
  }

  /**
   * Core CKEditor save handler: only apply Message box values to the parent editor.
   */
  function messageBoxEditorDialogSaveHandler(values) {
    invokeMessageBoxSaveCallback(values);
  }

  /**
   * Ensures editor:dialogsave and Ajax backup handlers are registered once.
   *
   * Values are filtered so entity embed saves do not insert an empty widget.
   */
  function bindMessageBoxSaveListeners() {
    if (saveListenersBound) {
      return;
    }
    saveListenersBound = true;

    $(window).on('editor:dialogsave.massInlineMessage', function (event, values) {
      invokeMessageBoxSaveCallback(values);
    });

    $(document).on('ajaxComplete.massInlineMessageSave', function (event, xhr, settings) {
      var url = (settings && settings.url) || '';
      if (url.indexOf(DIALOG_ROUTE_FRAGMENT) === -1 || !window.__massInlineMessageSaveCallback) {
        return;
      }
      try {
        var response = JSON.parse(xhr.responseText);
        if (!Array.isArray(response)) {
          return;
        }
        response.forEach(function (command) {
          if (command.command === 'editorDialogSave') {
            invokeMessageBoxSaveCallback(command.values);
          }
        });
      }
      catch (e) {
        // Ignore non-JSON responses.
      }
    });
  }

  window.MassInlineMessageDialog = window.MassInlineMessageDialog || {};
  window.MassInlineMessageDialog.invokeSaveCallback = invokeMessageBoxSaveCallback;
  window.MassInlineMessageDialog.isMessageBoxDialogSaveValues = isMessageBoxDialogSaveValues;
  window.MassInlineMessageDialog.editorDialogSaveHandler = messageBoxEditorDialogSaveHandler;

  /**
   * Stubs dialog('instance') until jQuery UI has opened the modal.
   */
  function guardDialogElement(element) {
    if (!element) {
      return;
    }
    var $dialog = $(element);
    if (typeof $dialog.dialog !== 'function' || $dialog.data('massInlineMessageDialogGuard')) {
      return;
    }
    var originalDialog = $dialog.dialog;
    $dialog.dialog = function (option) {
      if (option === 'instance') {
        var instance = originalDialog.call($dialog, option);
        if (!instance) {
          return {
            _focusedElement: null,
            _focusTabbable: function () {}
          };
        }
        return instance;
      }
      return originalDialog.apply($dialog, arguments);
    };
    $dialog.data('massInlineMessageDialogGuard', true);
  }

  /**
   * Guards every modal root that can receive nested CKEditor dialog content.
   */
  function guardDialogElementsForContext(context) {
    if (!context || context === document || !context.closest) {
      return;
    }
    var dialogEl = context.closest('.ui-dialog-content');
    if (dialogEl) {
      guardDialogElement(dialogEl);
    }
    var drupalModal = context.closest('#drupal-modal');
    if (drupalModal) {
      guardDialogElement(drupalModal);
    }
    var messageModal = context.closest('#mass-inline-message-modal');
    if (messageModal) {
      guardDialogElement(messageModal);
    }
  }

  function guardKnownModalRoots() {
    guardDialogElement(document.getElementById('drupal-modal'));
    guardDialogElement(document.getElementById('mass-inline-message-modal'));
  }

  /**
   * Guards core dialog.attach when nested modals attach before jQuery UI opens.
   *
   * Layout Paragraphs + Message box + entity embed calls attachBehaviors on
   * #drupal-modal content before dialog('instance') exists; core then throws
   * when setting _focusedElement on undefined.
   */
  function patchDialogAttachFocusGuard() {
    if (!Drupal.behaviors.dialog || Drupal.behaviors.dialog.__massInlineMessageAttachPatched) {
      return;
    }
    var originalAttach = Drupal.behaviors.dialog.attach.bind(Drupal.behaviors.dialog);

    Drupal.behaviors.dialog.attach = function (context, settings) {
      settings = settings || drupalSettings || {};
      settings.dialog = settings.dialog || {};
      guardDialogElementsForContext(context);
      guardKnownModalRoots();
      originalAttach(context, settings);
    };

    Drupal.behaviors.dialog.__massInlineMessageAttachPatched = true;
    Drupal.behaviors.dialog.__massInlineMessageOriginalAttach = originalAttach;
  }

  /**
   * entity-embed-dialog-alter.js calls attachBehaviors during embed steps.
   */
  function patchAttachBehaviorsGuard() {
    if (!Drupal.attachBehaviors || Drupal.attachBehaviors.__massInlineMessagePatched) {
      return;
    }
    var originalAttachBehaviors = Drupal.attachBehaviors;
    Drupal.attachBehaviors = function (context, settings) {
      patchDialogAttachFocusGuard();
      guardDialogElementsForContext(context);
      guardKnownModalRoots();
      return originalAttachBehaviors.call(this, context, settings);
    };
    Drupal.attachBehaviors.__massInlineMessagePatched = true;
  }

  /**
   * Ensures the dialog.attach guard is active before nested CKEditor dialogs open.
   */
  function patchCkeditor5OpenDialog() {
    if (!Drupal.ckeditor5 || Drupal.ckeditor5.__massInlineMessageOpenDialogPatched) {
      return;
    }
    var originalOpenDialog = Drupal.ckeditor5.openDialog.bind(Drupal.ckeditor5);
    Drupal.ckeditor5.openDialog = function (url, saveCallback, dialogSettings) {
      patchDialogAttachFocusGuard();
      return originalOpenDialog(url, saveCallback, dialogSettings);
    };
    Drupal.ckeditor5.__massInlineMessageOpenDialogPatched = true;
  }

  function syncNestedDialogStackClasses() {
    var hasMessageBox = !!document.querySelector('#mass-inline-message-dialog-form');
    document.body.classList.toggle('mass-inline-message-dialog-open', hasMessageBox);
    document.body.classList.toggle(
      'mass-inline-message-embed-open',
      hasMessageBox && !!document.querySelector('#drupal-modal .ui-dialog-content, #drupal-modal form.entity-embed-dialog, #drupal-modal form.media-entity-download-dialog')
    );
  }

  patchDialogAttachFocusGuard();
  patchAttachBehaviorsGuard();
  patchCkeditor5OpenDialog();
  guardKnownModalRoots();

  window.addEventListener('dialog:beforecreate', function () {
    patchDialogAttachFocusGuard();
    patchAttachBehaviorsGuard();
    guardKnownModalRoots();
  });

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
    }

    syncNestedDialogStackClasses();
  }

  Drupal.behaviors.massInlineMessageDialog = {
    attach: function (context) {
      patchDialogAttachFocusGuard();
      patchAttachBehaviorsGuard();
      guardKnownModalRoots();
      ensureMassInlineMessageModalContainer();
      bindMessageBoxSaveListeners();
      bindGlobalHandlers();

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
      if (event.target.id === 'drupal-modal' || event.target.querySelector('form.entity-embed-dialog, form.media-entity-download-dialog')) {
        var embedWrapper = event.target.closest('.ui-dialog');
        if (embedWrapper) {
          embedWrapper.classList.add('mass-inline-message-embed-dialog');
        }
        syncNestedDialogStackClasses();
      }
    }
  });

  window.addEventListener('dialog:afterclose', function () {
    window.requestAnimationFrame(syncNestedDialogStackClasses);
  });

  window.addEventListener('dialogButtonsChange', function (event) {
    if (event.target) {
      wireDialogContent(event.target);
    }
  });

})(jQuery, Drupal, drupalSettings);
