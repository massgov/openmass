/**
 * @file
 * Provides JavaScript for Mass Feedback Forms.
 * Handles submission to the Lambda feedback API instead of Formstack.
 */

(function ($, once) {
  'use strict';

  /**
   * Support feedback form submission to Lambda API with geolocation support.
   */
  Drupal.behaviors.massFeedbackForm = {
    attach: function (context) {

      // Cache for geolocation promise (to avoid multiple requests)
      let geoLocationPromise = null;
      let geoLocationPromiseStarted = false;

      /**
       * Get or create the geolocation promise.
       * Only requests geolocation once, subsequent calls return the same promise.
       *
       * @return {Promise} Promise resolving with geolocation data or rejection.
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
        const $self = $(element);
        const $form = $self.find('form').not('has-error');

        if (!$form.length) {
          return;
        }

        const feedback = $self[0];
        const $success = $self.find('#success-screen');
        const $submitBtn = $('input[type="submit"]', $form);
        const formAction = $form.attr('action');
        let isSubmitting = false;

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
       *
       * @return {Promise} Promise resolving with {latitude, longitude} object or rejection.
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
                longitude: position.coords.longitude.toString()
              });
            },
            function (error) {
              clearTimeout(timeoutId);
              reject(error);
            },
            {
              enableHighAccuracy: false,
              timeout: 10000,
              maximumAge: 300000 // 5 minutes cache
            }
          );
        });
      }

      /**
       * Submit feedback to Lambda API.
       *
       * @param {jQuery} $form The form element.
       * @param {string} formAction The API endpoint URL.
       * @param {Object} geoData The geolocation data {latitude, longitude}.
       * @param {jQuery} $success The success message element.
       * @param {Element} feedback The feedback container element.
       * @param {jQuery} $submitBtn The submit button element.
       * @param {Function} onComplete Callback when submission is complete.
       */
      function submitFeedback($form, formAction, geoData, $success, feedback, $submitBtn, onComplete) {
        const formData = new FormData($form[0]);

        // Get explain field - handle both visible and hidden textareas with same name
        // The form has two textareas with name="explain" (positive and negative feedback)
        // FormData.get() only returns the first one, so we need to get the visible one
        let explainField = '';
        const explainInputs = $form.find('textarea[name="explain"]');
        explainInputs.each(function () {
          const $textarea = $(this);
          // Check if textarea is visible (not hidden by CSS display:none or parent hidden class)
          if ($textarea.is(':visible') && !$textarea.closest('.feedback-response').hasClass('hidden')) {
            explainField = $textarea.val() || '';
          }
        });

        const payload = {
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

        fetch(formAction, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Accept-Language': navigator.language || 'en-US',
            'X-Requested-With': 'XMLHttpRequest',
            'User-Agent': navigator.userAgent
          },
          body: JSON.stringify(payload)
        })
          .then(function (response) {
            if (!response.ok) {
              return response.json().then(function (data) {
                throw {
                  status: response.status,
                  data: data
                };
              });
            }
            return response.json();
          })
          .then(function (data) {
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
       *
       * @param {jQuery} $form The form element.
       * @param {string} message The error message to display.
       */
      function showErrorMessage($form, message) {
        let $messages = $form.find('.messages');
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
    }
  };
})(jQuery, once);
