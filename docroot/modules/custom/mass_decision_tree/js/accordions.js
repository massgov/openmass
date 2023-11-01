/**
 * @file
 * Extends Drupal object with mass custom js objects
 *
 * Provides accordion functionality based on Mayflower accordion JS. Allows
 * accordions to be created on demand rather than just on page load.
 *
 */
(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.MassAccordions = {
    attach: function (context, settings) {
    },
    create: function (context) {
      var $elements = $(once('massAccordionCreate', '.js-accordion', context));
      $elements.each(function (index) {
        // To ensure applying js-accordion only once.
        // Mayflower adds js-accordion as data, so we
        // check if this data was applied or not.
        if ($(this).data('js-accordion')) {
          return;
        }
        $(this).data('js-accordion', 1);

        var $el = $(this);
        var $link = $el.find('.js-accordion-link').eq(0);
        var $content = $el.find('.js-accordion-content').eq(0);
        var id = $content.attr('id') || 'accordion' + (index + 1);
        var active = checkActive($el);
        var open = $el.hasClass('is-open');

        $content.attr('id', id);
        $link.attr('aria-expanded', open).attr('aria-controls', id);

        if (open) {
          // setup the inline display block
          $content.stop(true, true).slideDown();
        }

        $link.on('click', function (e) {
          if (active) {
            e.preventDefault();
            open = $el.hasClass('is-open');
            if (open) {
              $content.stop(true, true).slideUp();
            }
            else {
              $content.stop(true, true).slideDown();
            }
            $link.attr('aria-expanded', !open);
            $el.toggleClass('is-open');
          }
        });

        $(window).resize(function () {
          var temp = checkActive($el);

          if (temp !== active && !temp) {
            $content.removeAttr('style');
            $el.removeClass('is-open');
            $link.attr('aria-expanded', 'false');
          }

          active = temp;
        }).resize();
      });

      function checkActive($el) {
        var value = 'true';
        if ($el.length) {
          value = window.getComputedStyle($el[0], ':before').getPropertyValue('content').replace(/"/g, '');
        }
        return value === 'false' ? false : true;
      }
    }
  };

})(jQuery, Drupal);
