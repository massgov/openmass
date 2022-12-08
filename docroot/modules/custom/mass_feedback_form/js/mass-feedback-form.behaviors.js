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
      $('.ma__mass-feedback-form', context).each(function (index) {
        var $self = $(this);
        var feedback = $self[0];

        var $formOriginal = $self.find('form').not('has-error');
        // Checks to avoid bots submitting the form
        // On the first form, the Feedback manager lambda will populate this field,
        // so a populated field was a bot, we honey potted him/her/they
        if ($formOriginal.find('#field68798989').length && $('#field68798989').val()) {
          // We don't need to show the bot anything
          return false;
        }

        var $form = $self.find('form').not('has-error');
        var $success = $self.find('#success-screen');
        // This is to stop a double click submitting the form twice
        var $submitBtn = $('input[type="submit"]', $form);
        $form.submit(function () {
          $submitBtn.prop('disabled', true);
        });
        $form.on('submit', function (e) {
          $form.addClass('hidden');
          $success.removeClass('hidden')
          feedback.scrollIntoView();

        });

        $form.ajaxForm({
          data: {jsonp: 1},
          dataType: 'script',
          beforeSubmit: test
        });

        function test(data, $form) {
          console.log(data);
        }
        window['form' + $form.attr('id')] = {
          onPostSubmit: function (message) {
            // If MG_FEEDBACK_ID is 'uniqueId', then we are submitting the first (feedback) form
            // so we now need to set the MG_FEEDBACK_ID value with the ID returned from formstack.
            var submissionId = message.submission;
            console.log(message.submission);
            console.log(message);
            if ($('#' + MG_FEEDBACK_ID).val() === 'uniqueId') {
              $('#' + MG_FEEDBACK_ID).val(submissionId);
            }
            feedback.scrollIntoView();
          },
          onSubmitError: function (err) {
            var message = 'Submission Failure: ' + err.error;
            getMessaging($form).html(message);
          }
        };

      });
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
