/**
 * @file
 */

(function ($, Drupal, drupalSettings) {
  'use strict';
  Drupal.behaviors.massAlertBlocks = {

    /**
     * Drupal behavior.
     *
     * @param {HTMLDocument|HTMLElement} context
     * The context argument for Drupal.attachBehaviors()/detachBehaviors().
     * @param {object} settings
     * The settings argument for Drupal.attachBehaviors()/detachBehaviors().
     */
    attach: function (context, settings) {

      $('.mass-alerts-block', context).each(function () {
        var $this = $(this);
        var path = $this.data('alerts-path');

        if (path !== '/alerts/sitewide') {
          if ($('.main-content--full').length) {
            $this.insertAfter('.pre-content');
          }
        }

        if (path) {
          $.ajax({
            type: 'GET',
            url: path,
            cache: true,
            success: function (content) {
              $this.html(content);
              $(document).trigger('ma:AjaxPattern:Render', [{el: $this}]);
            }
          });
        }
      });
    }

  };
})(jQuery, Drupal, drupalSettings);
