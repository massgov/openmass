/**
 * @file
 * Support for contextual navigation wherever it is rendered.
 */

(function (window, document, $) {
  'use strict';
  var $contextual_nav = $('.contextual-nav').html();
  $('.contextual-nav').detach();
  $('li.js-util-nav-toggle:last-child .ma__utility-nav__content-body').prepend($contextual_nav);
})(window, document, jQuery);
