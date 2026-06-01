/**
 * @param $
 * @param Drupal
 * @param drupalSettings
 * @file
 * Keeps Message box dialog Save on the Ajax modal flow (Drupal 11).
 *
 * Dialog content loads via Ajax, so behaviors must bind without requiring
 * "body" in the Ajax insert context. Uses capture-phase handlers on the
 * mass-inline-message-dialog modal (same class of issue as entity embed).
 */
(function ($, Drupal, drupalSettings) {
  'use strict';

  var handlersBound = false;

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

  function submitDialogButtonViaAjax($button) {
    var button = $button.get(0);
    var $form = $button.closest('form.mass-inline-message-dialog-form');
    if (!button || !$form.length) {
      return false;
    }
    if ($form.data('massInlineMessageAjaxInFlight')) {
      return true;
    }
    $form.data('massInlineMessageAjaxInFlight', true);

    syncDialogBodyEditors($form.get(0));
    Drupal.attachBehaviors($form.get(0), drupalSettings);

    var ajaxInstance = getAjaxInstance(button);
    if (ajaxInstance) {
      ajaxInstance.eventResponse(button, $.Event(ajaxInstance.event || 'click'));
      window.setTimeout(function () {
        $form.removeData('massInlineMessageAjaxInFlight');
      }, 0);
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

    window.setTimeout(function () {
      $form.removeData('massInlineMessageAjaxInFlight');
    }, 0);
    return true;
  }

  function isMessageBoxDialog($dialog) {
    return $dialog.length && $dialog.find('#mass-inline-message-dialog-form').length > 0;
  }

  function getDialogFromElement(element) {
    var $dialog = $(element).closest('.ui-dialog');
    if (isMessageBoxDialog($dialog)) {
      return $dialog;
    }
    var $visible = $('.ui-dialog:visible').filter(function () {
      return $(this).find('#mass-inline-message-dialog-form').length > 0;
    });
    return $visible.last();
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

      var $dialog = getDialogFromElement(target);
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

    var form = dialogContent.querySelector('form.mass-inline-message-dialog-form');
    if (form) {
      Drupal.attachBehaviors(form, drupalSettings);
    }
  }

  Drupal.behaviors.massInlineMessageDialog = {
    attach: function (context) {
      bindGlobalHandlers();

      $(context).find('form.mass-inline-message-dialog-form').each(function () {
        Drupal.attachBehaviors(this, drupalSettings);
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
