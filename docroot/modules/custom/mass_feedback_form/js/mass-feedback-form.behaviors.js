/**
 * @file
 * Provides JavaScript for Mass Feedback Forms.
 */

/* global dataLayer */

(function ($) {
  'use strict';

  /**
   * Support a multi-step Feedback form.
   */
  Drupal.behaviors.massFeedbackForm = {
    attach: function (context) {
      // These IDs come from the formstack form. Login to formstack and see the following forms to confirm or edit ids:
      //   Forms -> Pilot -> Feedback - multi site.
      //   Forms -> Pilot -> Feedback-part2.
      var DID_YOU_FIND = 'field47054416';
      var LOOKING_FOR = 'field47054414';
      var LIKE_RESPONSE = 'field70611737';
      var EMAIL = 'field70611812';
      var PHONE = 'field70611804';
      var SURVEY_EMAIL = 'field68557501';
      var PLEASE_TELL_US = 'field47054414';

      // This field is used by the feedback manager to join the survey (second) with the first submission
      var MG_FEEDBACK_ID = 'field68557708';

      // For certain form inputs, use a value from the data layer.
      $('.data-layer-substitute', context).each(function (index) {
        var $this = $(this);
        var property = $this.val();
        var sub = '';

        for (var i = 0; i < dataLayer.length; i++) {
          if (typeof dataLayer[i][property] !== 'undefined') {
            sub = dataLayer[i][property];
          }
        }

        if (sub !== '' && typeof sub === 'string') {
          $this.val(sub);
        }
        $this.removeClass('data-layer-substitute');
      });

      // Process the multistep form.
      $('.feedback-steps', context).each(function (index) {
        var $self = $(this);
        var feedback = $self.find('#feedback')[0];
        var $steps = $self.find('.feedback-step');
        // Loop for each step.
        $steps.each(function (index) {
          var $step = $(this);
          var $form = $step.find('form');
          var nextId = $step.data('feedbackNext');
          // Setup steps that just allow choosing to continue.
          if ($form.attr('action') === '#') {
            $form.on('submit', function (e) {
              e.preventDefault();
              $step.addClass('hidden');
              $self.find('#' + nextId).removeClass('hidden');
              feedback.scrollIntoView();
            });
          }
          // Setup steps that submit to formstack via ajax (using jquery form).
          else {
            // This is to stop a double click submitting the form twice
            var $submitBtn = $('input[type="submit"]', $form);
            $form.submit(function () {
              $submitBtn.prop('disabled', true);
            });

            $form.ajaxForm({
              data: {jsonp: 1},
              dataType: 'script',
              beforeSubmit: validateForm
            });
            window['form' + $form.attr('id')] = {
              onPostSubmit: function (message) {
                // If MG_FEEDBACK_ID is 'uniqueId', then we are submitting the first (feedback) form
                // so we now need to set the MG_FEEDBACK_ID value with the ID returned from formstack.
                var submissionId = message.submission;
                if ($('#' + MG_FEEDBACK_ID).val() === 'uniqueId') {
                  $('#' + MG_FEEDBACK_ID).val(submissionId);
                }

                $step.addClass('hidden');
                $self.find('#' + nextId).removeClass('hidden');
                feedback.scrollIntoView();
              },
              onSubmitError: function (err) {
                var message = 'Submission Failure: ' + err.error;
                getMessaging($form).html(message);
              }
            };
          }
        });

      });

      // Hide contact link on success message if the user found what they were looking for.
      $('input[name="' + DID_YOU_FIND + '"]', context).on('change', function () {
        var $this = $(this);
        if ($this.val() === 'Yes') {
          var $textBoxNo = $this.closest('form').find('textarea[name="' + PLEASE_TELL_US + '"]');
          $textBoxNo.val('');
          $this.closest('form').find('[name="hidden_fields"]').val($textBoxNo.attr('id'));
        }
        else {
          $this.closest('form').find('[name="hidden_fields"]').val('');
        }
        var $contact = $this.closest('.feedback-steps').find('#feedback-success span');
        if ($contact.length > 0) {
          if ($this.val() === 'Yes') {
            $contact.addClass('js-hide');
          }
          else {
            $contact.removeClass('js-hide');
          }
        }
      });

      // Hide the email field on the survey if the user has already entered an email.
      $('input[name="' + EMAIL + '"]', context).on('change', function () {
        var hasEmail = this.value.length > 0;
        var $surveyEmail = $(this).closest('.feedback-steps').find('input[name="' + SURVEY_EMAIL + '"]');
        if ($surveyEmail.length > 0) {
          if (hasEmail) {
            $surveyEmail.parent().addClass('js-hide');
          }
          else {
            $surveyEmail.parent().removeClass('js-hide');
          }
        }
      });

      // Supply custom validation to support conditional fields.
      function validateForm(data, $form) {
        var validates = true;
        var message = '<p class="error">Please go back and fill in any required fields (marked with an *)</p>';

        // Switch validation based on presence of "Did you find ...".
        var $didYouFind = $form.find('[name="' + DID_YOU_FIND + '"]');
        if ($didYouFind.length > 0) {
          // Check if "Did you find ..." has a value.
          if (!$didYouFind.filter(':checked').length) {
            $didYouFind.addClass('error')
              .closest('fieldset').addClass('error c_radio');
            validates = false;
          }
          else if ($didYouFind.filter(':checked').val() === 'No') {
            var $lookingFor = $form.find('[name="' + LOOKING_FOR + '"]');
            if (!($lookingFor.val().length > 0)) {
              $lookingFor.addClass('error')
                .closest('fieldset').addClass('error');
              validates = false;
            }
          }
          // Check if "Would you like a response ..." exists and has a value.
          var $likeResponse = $form.find('[name="' + LIKE_RESPONSE + '"]');
          if ($likeResponse.length > 0 && !$likeResponse.filter(':checked').length && $didYouFind.filter(':checked').val() === 'No') {
            $likeResponse.addClass('error')
              .closest('fieldset').addClass('error c_radio');
            validates = false;
          }
          else if ($likeResponse.length > 0 && $likeResponse.filter(':checked').length > 0 && $likeResponse.filter(':checked').val() === 'Yes') {
            // Check if either the phone or email is populated.
            var $phone = $form.find('[name="' + PHONE + '"]');
            var $email = $form.find('[name="' + EMAIL + '"]');
            var validEmail = /^\w+([.-]?\w+)*@\w+([.-]?\w+)*(\.\w{2,3})+$/.test($email.val());
            if (!$phone.val().length > 0 && !validEmail) {
              validates = false;
              message = '<p class="error">Please add an email or a phone number to receive a response.</p>';
              $phone.addClass('error');
              $email.addClass('error');
            }
          }
        }

        // Checks to avoid bots submitting the form
        //
        // On the first form, the Feedback manager lambda will populate this field,
        // so a populated field was a bot, we honey potted him/her/they
        if ($form.find('#field68798989').length && $('#field68798989').val()) {
          // We don't need to show the bot anything
          return false;
        }

        // On the second form, the value will be assigned the formstack ID on submission
        // so a default value of 'uniqueId' is not acceptable since we always want the survey
        // to be tied to the first form
        if ($form.find('#' + MG_FEEDBACK_ID).length && $('#' + MG_FEEDBACK_ID).val() === 'uniqueId') {
          // We don't need to show the bot anything
          return false;
        }

        if (!validates) {
          getMessaging($form).html(message);
          var $submitBtn = $('input[type="submit"]', $form);
          $submitBtn.prop('disabled', false);
        }

        return validates;
      }

      // Handle the creation and management of the form messages.
      function getMessaging($form) {
        var $messages = $('.messages', $form);
        if (!$messages.length) {
          $form.find('input[type="submit"]').parent().prepend('<div class="messages" style="font-weight: bold; color: red"/>');
          $messages = $('.messages', $form);
        }
        return $messages;
      }

      // Remaining character count.
      // Need to set up separate presentations for sighted and screen reader users
      // since aria-live is announced only container which contains a change.
      // It doesn't announce nested containers to provide context.
      $('textarea[maxlength]').each(function () {
        var $el = $(this);
        var maxlength = $el.attr('maxlength');

        var remaining = maxlength - $el.val().length;
        var message = '<div aria-hidden="true"><span class="remainChar">' + remaining + '</span>/' + maxlength + '</div>';

        $el.wrap('<div class="ma__textarea__wrapper"></div>');

        // Generate ID for aria-live region.
        var randomId = Math.floor(Math.random() * 90000) + 10000;

        // Add a container for remaining char info.
        $el.parent().append(message + '<span role="region" aria-live="polite" class="remainingChar ma__visually-hidden">' + remaining + ' characters remaining</span>');

        // Associate text area and remaining charinfo container for aria-live region.
        $el.attr('aria-controls', randomId);
        $el.siblings('.remainingChar').attr('id', randomId);

        $el.next('.remainingChar').find('.remainChar').text(remaining);

        $el.on('keyup mouseup blur', function () {
          remaining = maxlength - $el.val().length;

          $el.next('div[aria-hidden]').find('.remainChar').text(remaining);
          $el.siblings('.remainingChar').text(remaining + ' characters remaining');
        });
      });

      // number restricted input based on it's pattern (this must run prior to type="number")
      $('input[type="text"][pattern="[0-9]*"]').on('keydown', function (e) {
        // Allow: delte(46), backspace(8), tab(9), escape(27), enter(13) and space(32))
        if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 32]) !== -1 ||
        // Allow: Ctrl/cmd+A
        (e.keyCode === 65 && (e.ctrlKey === true || e.metaKey === true)) ||
        // Allow: Ctrl/cmd+C
        (e.keyCode === 67 && (e.ctrlKey === true || e.metaKey === true)) ||
        // Allow: Ctrl/cmd+X
        (e.keyCode === 88 && (e.ctrlKey === true || e.metaKey === true)) ||
        // Allow: home, end, left, right
        (e.keyCode >= 35 && e.keyCode <= 39)) {
        // let it happen, don't do anything
          return;
        }
        // Ensure that it is a number and stop the keypress
        if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
          e.preventDefault();
        }
      });

      // number input type
      $('input[type="number"], .js-input-number').each(function () {
        var $el = $(this);
        var $plus = $('<button type="button" aria-label="increase value" class="ma__input-number__plus"></button>');
        var $minus = $('<button type="button" aria-label="decrease value" class="ma__input-number__minus"></button>');

        var value = $el.val();

        // if the input is not an html input and key restrictions
        if ($el.attr('type') !== 'number') {
          $el.on('keydown', function (e) {
            // Allow: delte(46), backspace(8), tab(9), escape(27), enter(13) and .(110 & 190))
            if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 ||
            // Allow: Ctrl/cmd+A
            (e.keyCode === 65 && (e.ctrlKey === true || e.metaKey === true)) ||
            // Allow: Ctrl/cmd+C
            (e.keyCode === 67 && (e.ctrlKey === true || e.metaKey === true)) ||
            // Allow: Ctrl/cmd+X
            (e.keyCode === 88 && (e.ctrlKey === true || e.metaKey === true)) ||
            // Allow: home, end, left, right
            (e.keyCode >= 35 && e.keyCode <= 39)) {
              // let it happen, don't do anything
              return;
            }
            // Ensure that it is a number and stop the keypress
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
              e.preventDefault();
            }
          });
        }

        $plus.on('click', function () {
          var value = parseInt($el.val().trim(), 10);

          if (value !== value) {
            value = 0;
          }

          $el.val(value + 1);
        });

        $minus.on('click', function () {
          var value = parseInt($el.val(), 10);

          if (value !== value) {
            value = 0;
          }

          $el.val(value - 1);
        });

        $el.wrap('<div class="ma__input-number"></div>');

        $el.parent().append($plus, $minus);
      });
    }
  };
})(jQuery);
