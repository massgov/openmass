/**
 * @file
 * Provides JavaScript for Mass Feedback Forms.
 * Handles submission to the Lambda feedback API instead of Formstack.
 */

/* global dataLayer, drupalSettings, once */

(function ($) {
  'use strict';

  /**
   * Support feedback form submission to Lambda API with geolocation support.
   */
  Drupal.behaviors.massFeedbackForm = {
    attach: function (context) {

      // Cache for geolocation promise (to avoid multiple requests)
      var geoLocationPromise = null;
      var geoLocationPromiseStarted = false;

      /**
       * Get or create the geolocation promise.
       * Only requests geolocation once, subsequent calls return the same promise.
       */
      function getGeolocationPromise() {
        if (!geoLocationPromiseStarted) {
          geoLocationPromiseStarted = true;
          geoLocationPromise = getMassgovGeolocation();
          // Ensure any promise rejection is handled to prevent unhandled rejection errors
          geoLocationPromise.catch(function (error) {
            console.warn('Geolocation request failed:', error.message || error);
            // Don't rethrow - let consumers handle it
            return {};
          });
        }
        return geoLocationPromise;
      }

      // Process feedback forms using Drupal's once() function
      once('massFeedbackForm', '.ma__mass-feedback-form', context).forEach(function (element) {
        var $self = $(element);
        var $form = $self.find('form').not('has-error');

        if (!$form.length) {
          return;
        }

        var feedback = $self[0];
        var $success = $self.find('#success-screen');
        var $submitBtn = $('input[type="submit"]', $form);
        var formAction = $form.attr('action');
        var isSubmitting = false;

        // Prevent double-click form submission
        $submitBtn.on('click', function (e) {
          if (isSubmitting) {
            e.preventDefault();
            return false;
          }
        });

        // Handle form submission
        $form.on('submit', function (e) {
          e.preventDefault();

          if (isSubmitting) {
            return false;
          }

          isSubmitting = true;
          $submitBtn.prop('disabled', true);

          // Wait for geolocation to complete (or fail), then submit
          var geoPromise = getGeolocationPromise();
          geoPromise.then(function (geoData) {
            submitFeedback($form, formAction, geoData, $success, feedback, $submitBtn, function () {
              isSubmitting = false;
            });
          }).catch(function (error) {
            console.warn('Geolocation error, submitting without coordinates:', error);
            submitFeedback($form, formAction, {}, $success, feedback, $submitBtn, function () {
              isSubmitting = false;
            });
          });

          return false;
        });
      });

      /**
       * Get user's geolocation if available.
       * Returns a promise that resolves with {latitude, longitude} or rejects.
       */
      function getMassgovGeolocation() {
        return new Promise(function (resolve, reject) {
          if (!navigator.geolocation) {
            reject(new Error('Geolocation not supported'));
            return;
          }

          var timeoutId = setTimeout(function () {
            reject(new Error('Geolocation timeout'));
          }, 10000); // 10 second timeout

          navigator.geolocation.getCurrentPosition(
            function (position) {
              clearTimeout(timeoutId);
              resolve({
                latitude: position.coords.latitude.toString(),
                longitude: position.coords.longitude.toString(),
              });
            },
            function (error) {
              clearTimeout(timeoutId);
              reject(error);
            },
            {
              enableHighAccuracy: false,
              timeout: 10000,
              maximumAge: 300000, // 5 minutes cache
            },
          );
        });
      }

      /**
       * Submit feedback to Lambda API.
       */
      function submitFeedback($form, formAction, geoData, $success, feedback, $submitBtn, onComplete) {
        var formData = new FormData($form[0]);

        // Get explain field - handle both visible and hidden textareas with same name
        // The form has two textareas with name="explain" (positive and negative feedback)
        // FormData.get() only returns the first one, so we need to get the visible one
        var explainField = '';
        var explainInputs = $form.find('textarea[name="explain"]');
        explainInputs.each(function () {
          var $textarea = $(this);
          // Check if textarea is visible (not hidden by CSS display:none or parent hidden class)
          if ($textarea.is(':visible') && !$textarea.closest('.feedback-response').hasClass('hidden')) {
            explainField = $textarea.val() || '';
          }
        });

        var payload = {
          node_id: parseInt(formData.get('node_id')) || 0,
          info_found: formData.get('info_found') === 'Yes',
          explain: explainField,
          url: window.location.href,
          timestamp: new Date().toISOString(),
        };

        // Add geolocation if available
        if (geoData && geoData.latitude && geoData.longitude) {
          payload.latitude = geoData.latitude;
          payload.longitude = geoData.longitude;
        }

        console.log('Submitting feedback:', payload);

        fetch(formAction, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Accept-Language': navigator.language || 'en-US',
            'X-Requested-With': 'XMLHttpRequest',
            'User-Agent': navigator.userAgent,
          },
          body: JSON.stringify(payload),
        })
          .then(function (response) {
            if (!response.ok) {
              return response.json().then(function (data) {
                throw {
                  status: response.status,
                  data: data,
                };
              });
            }
            return response.json();
          })
          .then(function (data) {
            console.log('Feedback submitted successfully:', data);

            // Show success screen
            $form.addClass('hidden');
            $success.removeClass('hidden');
            feedback.scrollIntoView({ behavior: 'smooth' });

            // Reset form after delay
            setTimeout(function () {
              $form.removeClass('hidden');
              $success.addClass('hidden');
              $form[0].reset();
              $submitBtn.prop('disabled', false);
            }, 5000);
          })
          .catch(function (error) {
            console.error('Feedback submission failed:', error);

            var errorMessage = 'Unable to submit your feedback. Please try again later.';
            if (error.status === 400 && error.data && error.data.errors) {
              errorMessage = 'Submission error: ' + error.data.errors.join(', ');
            }

            showErrorMessage($form, errorMessage);
            $submitBtn.prop('disabled', false);
          })
          .finally(function () {
            onComplete();
          });
      }

      /**
       * Display error message in the form.
       */
      function showErrorMessage($form, message) {
        var $messages = $form.find('.messages');
        if (!$messages.length) {
          $form.prepend('<div class="messages" style="font-weight: bold; color: #d73d32; margin-bottom: 20px;"/>');
          $messages = $form.find('.messages');
        }
        $messages.html(message).show();

        // Auto-hide after 5 seconds
        setTimeout(function () {
          $messages.fadeOut();
        }, 5000);
      }
    },
  };
})(jQuery);
