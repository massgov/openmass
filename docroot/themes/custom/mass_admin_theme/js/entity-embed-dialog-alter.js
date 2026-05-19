/**
 * @file
 * Entity embed dialog alter
 *
 * Why this exists:
 * On Drupal 11, entity-embed dialog action buttons can lose Ajax bindings
 * after dialog rebuilds, which can trigger full-page submit/redirect instead
 * of staying in the modal flow. This script forces those actions through
 * Drupal Ajax consistently for Next/Embed/Back interactions.
 * Covered by: mass_content EntityEmbedDialogAjaxBridgeTest.
 *
 * This also supports collapsible navigable is the 'is-collapsible' class is
 * added to the main element, and a target element is included.
 */
(function ($, Drupal, drupalSettings) {

  'use strict';

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

  function submitDialogButtonViaAjax($button) {
    var button = $button.get(0);
    var $form = $button.closest('form.entity-embed-dialog');
    if (!button || !$form.length) {
      return;
    }
    if ($form.data('massEntityEmbedAjaxInFlight')) {
      return;
    }
    $form.data('massEntityEmbedAjaxInFlight', true);

    // Re-attach once to pick up newly rebuilt dialog actions.
    Drupal.attachBehaviors($form.get(0), drupalSettings);

    var ajaxInstance = getAjaxInstance(button);
    if (ajaxInstance) {
      ajaxInstance.eventResponse(button, $.Event(ajaxInstance.event || 'click'));
      window.setTimeout(function () {
        $form.removeData('massEntityEmbedAjaxInFlight');
      }, 0);
      return;
    }

    // Last-resort fallback: force Drupal Ajax execution for this button.
    var ajax = Drupal.ajax({
      base: button.id || ('mass-entity-embed-' + Date.now()),
      element: button,
      url: $form.attr('action'),
      event: 'click',
      setClick: true,
      progress: {type: 'fullscreen'}
    });
    ajax.eventResponse(button, $.Event('click'));

    // Release the lock after the current Ajax cycle starts.
    window.setTimeout(function () {
      $form.removeData('massEntityEmbedAjaxInFlight');
    }, 0);
  }

  function getSubmitterFromEvent(event, $form) {
    var submitter = event.originalEvent && event.originalEvent.submitter;
    if (submitter) {
      return $(submitter);
    }
    var $active = $(document.activeElement);
    if ($active.closest($form).length) {
      return $active;
    }
    return $form.find('.js-button-primary, .js-button-embed, .form-submit').first();
  }

  function getActiveEmbedDialogForm() {
    var $dialog = $('.ui-dialog:visible').last();
    if (!$dialog.length) {
      return $();
    }
    return $dialog.find('form.entity-embed-dialog').first();
  }

  function submitByOperationLabel(opLabel, visibleButton) {
    var $form = getActiveEmbedDialogForm();
    if (!$form.length) {
      return;
    }

    var $hiddenSubmitters = $form
      .find('input[type="submit"][name="op"], button[type="submit"][name="op"]')
      .filter(':visible, [style*="display: none"], .js-form-submit');
    var $hiddenSubmitter = $hiddenSubmitters.filter(function () {
        return ($(this).val() || $(this).text() || '').trim() === opLabel;
      }).first();

    // Fallback: map by button index in dialog pane to hidden form submitter.
    if (!$hiddenSubmitter.length && visibleButton) {
      var $paneButtons = $(visibleButton)
        .closest('.form-actions')
        .find('.js-form-submit');
      var visibleIndex = $paneButtons.index(visibleButton);
      if (visibleIndex >= 0 && $hiddenSubmitters.eq(visibleIndex).length) {
        $hiddenSubmitter = $hiddenSubmitters.eq(visibleIndex);
      }
    }

    // Final fallback: prefer primary action, then last action.
    if (!$hiddenSubmitter.length) {
      $hiddenSubmitter = $hiddenSubmitters.filter('.button--primary').first();
    }
    if (!$hiddenSubmitter.length) {
      $hiddenSubmitter = $hiddenSubmitters.last();
    }

    if ($hiddenSubmitter.length) {
      submitDialogButtonViaAjax($hiddenSubmitter);
    }
  }

  /**
   * Initialise the imageStyle JS.
   */
  Drupal.behaviors.imageStyle = {
    attach: function (context, settings) {
      var image_style = $('.js-form-item-attributes-data-entity-embed-display-settings-image-style select');
      var align_fieldset = $('fieldset.fieldgroup[data-drupal-selector="edit-attributes-data-align"]');

      image_style.find('option').each(function () {
        var value = $(this).attr('value').trim();
        if (value !== 'embedded_full_width' && value !== 'embedded_half_width') {
          image_style.find('option[value="' + value + '"]').remove();
        }
      });

      hide_show_align(image_style, align_fieldset);

      image_style.on('change', function () {
        hide_show_align($(this), align_fieldset);
      });

      // File Browser selection triggers this callback to advance to the next
      // step. On Drupal 11, there are cases where the injected Next button
      // misses Ajax binding and falls back to a full form submit/redirect.
      if (Drupal.entityEmbedDialog) {
        Drupal.entityEmbedDialog.selectionCompleted = function () {
          var $dialogForm = $('form.entity-embed-dialog');
          var $nextButton = $dialogForm.find('.js-button-next').first();

          if (!$nextButton.length) {
            return;
          }

          submitDialogButtonViaAjax($nextButton);
        };
      }

      // Handle second-step actions (Embed/Back) the same way.
      if (!window.__massEntityEmbedSecondStepBound) {
        window.__massEntityEmbedSecondStepBound = true;
        $(document).on('click.massEntityEmbedDialog', 'form.entity-embed-dialog .form-actions input[type="submit"], form.entity-embed-dialog .form-actions button[type="submit"]', function (event) {
          var $button = $(this);
          event.preventDefault();
          event.stopImmediatePropagation();
          submitDialogButtonViaAjax($button);
        });

        // Drupal dialog renders visible action buttons in the dialog pane,
        // outside of the form. Route those clicks to the hidden form submit
        // elements that carry the actual Ajax bindings.
        $(document).on('click.massEntityEmbedDialog', '.ui-dialog:visible .ui-dialog-buttonpane .form-actions .js-form-submit', function (event) {
          var $button = $(this);
          var opLabel = ($button.val() || $button.text() || '').trim();
          event.preventDefault();
          event.stopImmediatePropagation();
          submitByOperationLabel(opLabel, this);
        });

        // Some submit flows bypass click handlers (e.g. Enter key, direct submit).
        // Force those paths through the same Ajax-only button execution.
        $(document).on('submit.massEntityEmbedDialog', 'form.entity-embed-dialog', function (event) {
          var $form = $(this);
          var $submitter = getSubmitterFromEvent(event, $form);

          if ($form.data('massEntityEmbedAjaxInFlight')) {
            return;
          }
          if (!$submitter.length) {
            return;
          }

          event.preventDefault();
          event.stopImmediatePropagation();
          submitDialogButtonViaAjax($submitter);
        });
      }
    }
  };

  /**
   * Helper function to hide and show the alignment fieldset.
   */
  function hide_show_align(trigger, target) {
    if (trigger.val() === 'embedded_full_width') {
      target.hide();
    }
    else if (trigger.val() === 'embedded_half_width') {
      target.show();
    }
  }

})(jQuery, Drupal, drupalSettings);
