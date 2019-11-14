/**
 * @file
 * [dev] Move user focus to top when adding a new "paragraph"
 *
 * The scrolls the page when a new paragraph type is entered.
 */
(function ($, Drupal) {

  'use strict';

  /**
   * Listen for 'field add more' submit and scroll to content.
   */
  $(document).ajaxStop(function (e) {
    var button = $(this).context.activeElement;
    if ($('.ajax-new-content').length && $(button).hasClass('field-add-more-submit')) {
      $('html, body').animate({
        scrollTop: $(button).closest('.clearfix').siblings('table').find('.ajax-new-content').offset().top - ($(window).height() / 2)
      }, 500);
      return false;
    }
  });

})(jQuery, Drupal);
