/* global dataLayer */

/**
 * @file
 * Defines binder navigation continuity.
 */
(function ($, Drupal, drupalSettings) {
  'use strict';

  /**
   * Active binder management for navigation.
   */
  Drupal.behaviors.massContentBinderStore = {
    attach: function (context) {

      // Check if a binder is active.
      var binder = sessionStorage.getItem('binder');
      var $activeBinder;
      // Check to see if the active binder relates to this page.
      if (binder && binder.length > 0) {
        $activeBinder = $(".referencing-binders.hidden[data-id='" + binder + "']");
      }
      // If there isn't an active binder or if the active binder does not apply to this page,
      // check for a single binder. If there is only one binder, it should be active.
      if (binder === null || $activeBinder && $activeBinder.length < 1) {
        var $referencingBinders = $('.referencing-binders');
        if ($referencingBinders.length <= 2) {
          binder = $referencingBinders.data('id');
          sessionStorage.setItem('binder', binder);
        }
      }
      // If the above has determined a binder, show the related navigation.
      if (binder) {
        $(".referencing-binders.hidden[data-id='" + binder + "']", context).removeClass('hidden');
        // If working in two different tabs or windows with different binders loaded, allow a page refresh
        // to stay on the same binder.
        $('body').on('pageHide', function (event) {
          sessionStorage.setItem('binder', binder);
        });
      }

      // Set the binder when binder navigation is clicked. This allows multiple binders to be
      // open in separate tabs and function independently.
      $('.referencing-binders', context).on('click', 'a', function (event) {
        var $referencingBinder = $(this).closest('.referencing-binders');
        if ($referencingBinder.length > 0) {
          sessionStorage.setItem('binder', $referencingBinder.data('id'));
        }
      });

      // Make sure that refreshing a page does not update the binder from a separate tab or window.
      $('.ma__toc--hierarchy__container', context).on('click', 'a', function (event) {
        sessionStorage.setItem('binder', dataLayer[0].entityId);
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
