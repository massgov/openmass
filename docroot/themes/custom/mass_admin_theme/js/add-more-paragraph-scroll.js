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
   *
   * This behavior is named with a ZZ so it falls after AJAX.
   */
  Drupal.behaviors.ZZaddMoreParagraphScroll = {
    attach: function (context) {
      // Find all paragraphs in the current context and iterate through
      // them to modify their ajax success callback.
      $('.field--widget-entity-reference-paragraphs .field-add-more-submit', context).each(function () {
        var id = $(this).attr('id');
        var matchingInstances = Drupal.ajax.instances.filter(function (instance) {
          return instance && instance.selector === '#' + id;
        });

        matchingInstances.forEach(function (instance) {
          // Replace the ajax success callback with our own, but save the
          // original (bound to the Ajax object) so we can use it later.
          var originalSuccess = instance.success;
          instance.success = function (response, status) {
            // Grab the original wrapper, which is about to be replaced, and
            // calculate the offset from the parent. This will stay consistent
            // even after the original has been replaced.
            var $wrapper = $(this.wrapper);
            var $parent = $wrapper.parent();
            var idx = $parent.index($wrapper);

            // Invoke the original success callback.
            var ret = originalSuccess.call(this, response, status);

            // Grab the top of the new paragraph and bring it to top minus height of our nav.
            var $input = $('.ajax-new-content', $parent.eq(idx));
            if ($input.length) {
              $('html, body').animate(
                {scrollTop: $input.offset().top - 116},
                333, // Scroll duration
                function () {
                  $input.focus();
                }
              );
            }

            return ret;
          };
        });
      });
    }
  };

})(jQuery, Drupal);
