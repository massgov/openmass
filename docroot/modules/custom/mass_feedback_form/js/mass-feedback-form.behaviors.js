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
        var id = Date.now() + Math.floor(Math.random() * 1000);
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
            $step.find('.unique-id-substitute').each(function (index) {
              var $this = $(this);
              $this.val(id);
              $this.removeClass('unique-id-substitute');
            });

            $form.ajaxForm({
              data: {jsonp: 1},
              dataType: 'script',
              beforeSubmit: validateForm
            });
            window['form' + $form.attr('id')] = {
              onPostSubmit: function (message) {
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

        if (!validates) {
          getMessaging($form).html(message);
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
    }
  };
})(jQuery);
