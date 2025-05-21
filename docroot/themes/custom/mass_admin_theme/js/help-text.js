/**
 * @file
 * Help text Javascript
 *
 * Functionality needed to improve the display of help text.
 */
(function ($, Drupal) {

  'use strict';

  /**
   * Set description field in multiple value fields/tables.
   */
  Drupal.behaviors.helpTextTableDescriptions = {
    attach: function (context, settings) {
      var $table = $('form.node-form table, form.media-form table');
      $table.each(function () {
        var $next = $(this).next();
        if ($next.hasClass('description')) {
          var $row = $(this).find('.mass-description');
          var $nextrow = $row.parent().next();
          if ($nextrow) {
            $row.attr('colspan', $nextrow.children('td').length);
          }
          $row.parent().removeClass('visually-hidden');
          $row.html($next.html());
          $next.hide();
        }
      });
    }
  };

  /**
   * Set description field above textarea elements.
   */
  Drupal.behaviors.helpTextTextAreaDescriptions = {
    attach: function (context, settings) {
      // Omit context from this lookup to allow fixing help text
      // for paragraph fields when they are loaded via AJAX.
      var $drupalDesc = $('form.node-form .form-item__description');
      $drupalDesc.each(function () {
        var $target = $(this).parent();
        if ($target.hasClass('text-full')) {
          var $massDesc = $target.find('.mass-description', context);
          if ($massDesc.length) {
            $(this).addClass('description');
            $target.find('label').first().after($(this));
          }
        }
      });
    }
  };

  /**
   * Set description field above datetime elements.
   */
  Drupal.behaviors.helpTextDateTimeDescriptions = {
    attach: function (context, settings) {
      var $drupalDesc = $('form.node-form .field--type-daterange .description', context);
      $drupalDesc.each(function () {
        var helpText = '<div class="description">' + $(this).html() + '</div>';
        var $target = $(this).parent().parent();
        if ($target.hasClass('form-wrapper')) {
          $target.find('legend', context).after(helpText);
          $(this).hide();
        }
      });
    }
  };

})(jQuery, Drupal);
