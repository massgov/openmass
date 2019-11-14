/**
 * @file
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.draggableTableRowHoverState = {
    attach: function (context, settings) {
      var $hoverTrigger = $('.tabledrag-handle', context);
      $hoverTrigger.hover(
        function () {
          $(this).closest('tr').addClass('hover');
        },
        function () {
          $(this).closest('tr').removeClass('hover');
        }
      );
    }
  };

})(jQuery, Drupal);
