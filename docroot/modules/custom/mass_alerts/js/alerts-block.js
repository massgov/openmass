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
        var removeContainer = false;

        if (path !== '/alerts/sitewide') {

          var nodeType = settings.mass_alerts.node.type;
          var positioned = false;

          if (nodeType === 'how_to_page') {
            if ($('.ma__page-header__optional-content').length) {
              $this.insertBefore('.ma__page-header__optional-content');
              removeContainer = true;
              positioned = true;
            }
          }
          else if (nodeType === 'person') {
            if ($('.ma__page-intro').length) {
              $this.insertAfter('.ma__page-intro');
              removeContainer = true;
              positioned = true;
            }
          }

          if (!positioned) {

            if ($('.ma__illustrated-header').length) {
              $this.insertAfter('.ma__illustrated-header');
            }
            else if ($('.ma__page-header').length) {
              $this.insertAfter('.ma__page-header');
            }
            else if ($('.ma__organization-navigation').length) {
              $this.insertAfter('.ma__organization-navigation');
            }
            else if ($('.ma__page-banner').length) {
              $this.insertAfter('.ma__page-banner');
            }
            else if ($('.pre-content').length) {
              $this.insertAfter('.pre-content');
            }
          }
        }


        if (path) {
          $.ajax({
            type: 'GET',
            url: path,
            cache: true,
            success: function (content) {
              $this.html(content);
              if (removeContainer) {
                $this.find('.ma__page-banner__container').removeClass('ma__page-banner__container');
              }
              $(document).trigger('ma:AjaxPattern:Render', [{el: $this}]);
            }
          });
        }
      });
    }

  };
})(jQuery, Drupal, drupalSettings);
