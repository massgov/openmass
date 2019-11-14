(function ($) {
  'use strict';

  Drupal.behaviors.massMediaDeleteConfirm = {
    attach: function (context) {
      $('input[data-remove-confirm]', context).each(function () {
        var element = this;

        var ajaxInstance = Drupal.ajax.instances.find(function (instance) {
          return instance && instance.element === element;
        });

        var oldHandler = ajaxInstance.options.beforeSubmit;
        ajaxInstance.options.beforeSubmit = function () {
          var message = Drupal.t('This file (@name) will be deleted from Mass.gov once you save this page. If you did not mean to do this, click the “Cancel” button.', {
            '@name': $(element).data('remove-confirm')
          });
          return window.confirm(message) && oldHandler.call(this, arguments);
        };
      });
    }
  };
}(jQuery));
