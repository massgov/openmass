/**
 * @file
 * Attaches mass_site_map behaviors to the entity form.
 */
(function ($) {

  'use strict';

  Drupal.behaviors.mass_site_mapForm = {
    attach: function (context) {
      $('#edit-field-publish-bio-page-value').bind('change', function () {
        // Set the attributes and let simple sitemap do the work.
        if ($(this).prop('checked')) {
          $('#edit-simple-sitemap-index-content-0').removeAttr('checked');
          $('#edit-simple-sitemap-index-content-1').attr('checked', 'checked');
        }
        else {
          $('#edit-simple-sitemap-index-content-1').removeAttr('checked');
          $('#edit-simple-sitemap-index-content-0').attr('checked', 'checked');
        }
        $('#edit-simple-sitemap-index-content').trigger('change');
      });
    }
  };
})(jQuery);
