/**
 * @file
 * Toggles class on layout paragraph builder when 'Page template' is 'Visual story'.
 */
(function ($, Drupal, once) {

  'use strict';

  /**
   * Toggle `visual_story` class based on selected page template.
   */
  Drupal.behaviors.toggleVisualStoryClass = {
    attach(context) {
      const selectEls = once('visual-story-select', '#edit-field-page-template', context);
      const targetEls = once('visual-story-target', '.js-lpb-component-list.info_details', context);

      if (selectEls.length && targetEls.length) {
        const $select = $(selectEls[0]);
        const $target = $(targetEls[0]);

        // Add or remove the class based on current value.
        const updateClass = () => {
          const isVisual = $select.val() === 'visual_story';
          $target.toggleClass('visual_story', isVisual);
        };

        // Run immediately on page load.
        updateClass();

        // Run again on change.
        $select.on('change', updateClass);
      }
    }
  };

})(jQuery, Drupal, once);
