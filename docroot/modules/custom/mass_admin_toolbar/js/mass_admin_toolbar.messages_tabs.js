/**
 * @file
 * Collapse messages on scroll.
 *
 */

(function ($) {

  'use strict';

  Drupal.behaviors.massDashboardTabs = {
    attach: function (context) {

      var $messages = $('.mass-dashboard-tabs__messages');
      var $tabs = $('.mass-dashboard-tabs__tabs');

      if ($messages.length) {
        var messageBottom = $messages.offset().top;

        var messagesOpen = true;

        var $toggle = $('<a href="#" class="mass-dashboard-tabs__trigger">Hide Messages</a>');
        if ($messages.children().length) {
          $tabs.prepend($toggle);
        }

        $toggle.click(function (e) {
          e.preventDefault();
          $(document).trigger('mass_dashboard.toggle');
        }).mouseout(function () {
          // Lose focus on the trigger when the mouse leaves. Using .blur() in the
          // click handler breaks menus for users who are tabbing through.
          $(this).blur();
        });

        $(document).on('mass_dashboard.toggle', function () {
          if (messagesOpen) {
            close();
          }
          else {
            open();
          }
        });
        var open = function () {
          messagesOpen = true;
          $messages.slideDown('slow', function () {
            $toggle.removeClass('is-closed');
            $toggle.text('Hide Messages');
            $(window).on('scroll.mass_dashboard_tabs', handleScroll);
          });
        };
        var close = function () {
          messagesOpen = false;
          $(window).off('scroll.mass_dashboard_tabs', handleScroll);
          $messages.slideUp('slow', function () {
            $toggle.addClass('is-closed');
            $toggle.text('Show Messages');
          });
        };

        var handleScroll = function () {
          if ($(window).scrollTop() > messageBottom) {
            // Reevaluate message height because user interactions can change it,
            // but we don't want to calculate this on every scroll event.
            messageBottom = $messages.offset().top + $messages.outerHeight(true);
            if ($(window).scrollTop() > messageBottom) {
              close();
            }
          }
        };
        $(window).on('scroll.mass_dashboard_tabs', handleScroll);
      }
    }
  };

})(jQuery);
