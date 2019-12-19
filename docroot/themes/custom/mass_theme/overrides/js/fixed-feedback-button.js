/**
 * @file
 * Support pages that do not have feedback forms.
 */

// eslint-disable-next-line no-unused-vars
/* global dataLayer */

(function ($) {
  'use strict';

  /**
   * Indicate with a body class when there is no feedback form.
   */
  Drupal.behaviors.massFixedFeedbackButton = {
    attach: function (context) {
      if (context === document) {
        if ($('#feedback', context).length < 1) {
          $('body', context).addClass('no-feedback');
        }
      }
    }
  };
})(jQuery);
