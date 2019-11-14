/**
 * @file
 * Describes a specific FormStack feedback form embedded using bare
 * HTML.
 *
 * Caution: if the form is updated upstream, it must be
 * updated here as well. We have to handle all validation, ajax,
 * and styling here, since we're not using any of Formstack's assets.
 * This decision was made for front-end performance reasons, and to
 * allow embedding of multiple Formstack forms in a page.
 */

(function ($) {
  'use strict';

  var FORM_ID = 2521317;
  var RADIO_ID = 47054416;
  var YES_FEEDBACK_ID = 52940022;
  var NO_FEEDBACK_ID = 47054414;
  var REFERER_ID = 47056299;

  $(document).ready(function () {
    $('#fsForm' + FORM_ID).each(function () {
      var $form = $(this);
      var $radio = $('input[name="field' + RADIO_ID + '"]', $form);
      var $textBoxYes = $('#field' + YES_FEEDBACK_ID, $form);
      var $textBoxNo = $('#field' + NO_FEEDBACK_ID, $form);
      var $hiddenFields = $('[name="hidden_fields"]', $form);

      // Set referer value.
      $('#field' + REFERER_ID, this).val(location.href);

      // Make the form AJAXy.
      $form.ajaxForm({
        // Add jsonp parameter when using ajax submission.
        data: {jsonp: 1},
        // Interpret received data as a script (JSONP).
        dataType: 'script',
        // Validate prior to submission.
        beforeSubmit: validateForm
      });

      // Add field swapping logic to the textareas.
      $radio.change(handleRadioChange);

      function handleRadioChange() {
        // Remove the existing character countdown box.
        $("div[id^='accessibleCharCount-wrapper-']").remove();
        // Clear the error message from the previous validation.
        $('.error', $form).removeClass('error');
        $('.messages', $form).remove();
        switch ($(this).val()) {
          case 'Yes':
            $('.radio-no', $form).addClass('hide');
            $('.radio-yes', $form).removeClass('hide');
            // Add character countdown.
            $textBoxYes.accessibleCharCount();
            $textBoxYes.removeAttr('disabled');
            $textBoxNo.val('');
            $textBoxNo.attr('aria-describedby', 'feedback-note');
            $textBoxNo.attr('disabled', 'disabled');
            $textBoxNo.removeAttr('required');
            $hiddenFields.val($textBoxNo.attr('id'));
            clearRadioError($radio);
            break;

          case 'No':
            $('.radio-yes', $form).addClass('hide');
            $('.radio-no', $form).removeClass('hide');
            // Add character countdown.
            $textBoxNo.accessibleCharCount();
            $textBoxNo.removeAttr('disabled');
            $textBoxNo.attr('required', 'required');
            $textBoxYes.val('');
            $textBoxYes.attr('aria-describedby', 'feedback-note2');
            $textBoxYes.attr('disabled', 'disabled');
            $hiddenFields.val($textBoxYes.attr('id'));
            clearRadioError($radio);
        }
      }

      // Create a global representing the form object.  This is to handle
      // JSONP callbacks appropriately.  See http://static.formstack.com/forms/js/3/scripts.js
      window['form' + FORM_ID] = {
        onSubmitError: function (err) {
          getMessaging($form).html('<p class="error">' + err.error + '</p>');
        },
        onPostSubmit: function (messageObj) {
          $form.html($(messageObj.message));
        }
      };
    });

  });

  // Validation Handler.
  function validateForm(data, $form) {
    var validates = true;
    data.forEach(function (field) {
      // Check for empty required fields. Required status is determined
      // by the "required" attribute being set on an input.
      if (field.required && !field.value.length > 0) {
        // Add Error attribute for easy theming and accessibility.
        $('[name="' + field.name + '"]', $form)
          .addClass('error')
          .closest('fieldset').addClass('error');

        validates = false;
      }
    });
    // Check the first question is answered.
    var $radio = $('[name="field' + RADIO_ID + '"]', $form);
    if (!$radio.filter(':checked').length) {
      validates = false;
      $radio.addClass('error')
        .closest('fieldset').addClass('error c_radio');
    }
    if (!validates) {
      getMessaging($form).html('<p class="error">Please fill in a valid value for all required fields</p>');
    }

    return validates;
  }

  function getMessaging($form) {
    var $messages = $('.messages', $form);
    if (!$messages.length) {
      $form.prepend('<div class="messages"/>');
      $messages = $('.messages', $form);
    }
    return $messages;
  }

  function clearRadioError($radio) {
    $radio.removeClass('error')
      .closest('fieldset').removeClass('error c_radio');
  }

})(jQuery);
