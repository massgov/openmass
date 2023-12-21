(function ($) {
  'use strict';

  Drupal.behaviors.userwayForm = {
    attach: function (context) {
      // Process the form.
      $('form#fsForm5560495', context).each(function (index) {
        var $form = $(this);
        // This is to stop a double click submitting the form twice
        var $submitBtn = $('button[type="submit"]', $form);
        $form.submit(function () {
          $submitBtn.prop('disabled', true);
        });

        $form.ajaxForm({
          data: {jsonp: 1},
          dataType: 'script',
          success: function(response) {
            $form.addClass('hidden');
            $form.parent().append('<p>Thank you for providing your email.</p>')
          },
          error: function(jqXHR, textStatus, errorThrown) {
            // Handle error scenario
            $form.addClass('hidden');
            $form.parent().append('<p style="color: red;">Something went wrong. Please refresh the page and try again.</p>');
            $submitBtn.prop('disabled', false);
          }
        });


      });
    }
  }
})(jQuery);
