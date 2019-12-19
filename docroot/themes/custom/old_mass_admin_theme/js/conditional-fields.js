/**
 * @file
 * Override field configuration based on given conditions in content types.
 *
 * Supplement Conditional Fields module for changes the module doesn't cover.
 */
(function ($, Drupal) {

  'use strict';

  /**
   * Override field configuration in News.
   */
  (function overrideNews() {
    // This grabs the text of the field to depend on.
    var selectedText = $('#edit-field-news-type option:checked').text();


    $('.layout-node-form').find('label').each(function () {

      /**
     * Override the field_news_body label based on selected option of
     * field_news_type in News.
     */
      if ($(this).attr('for') === 'edit-field-news-body-0-value') {
        if (selectedText !== 'Press Release') {
          $(this).text('Overview');
        }
        else {
          $(this).text('Content');
        }
      }

      /**
     * Add an asterisk to indicate 'required' for field_news_location label in
     * News.
     */
      if ($(this).attr('for') === 'edit-field-news-location-0-value') {
        if (selectedText !== 'Press Release') {
          $(this).removeAttr('class');
        }
        else {
          $(this).addClass('js-form-required form-required');
        }
      }
    });

    /**
   * Add an asterisk to indicate 'required' to tab and Media contacts field in
   * News.
   */
    $('li.horizontal-tab-button').each(function () {
      // Limit this to only the News node; this fixes the asterisk
      // on the Contact tab for the How-to Page.
      if (!document.getElementById('node-news-form')) { return; }
      var hoizontalTabLabel = $(this).find('strong');
      if (hoizontalTabLabel.text() === 'Contacts') {
        if (selectedText === 'Press Release' || selectedText === 'Press Statement') {
          hoizontalTabLabel.addClass('form-required');
          $('#edit-field-news-media-contac > strong').addClass('form-required');
        }
        else {
          hoizontalTabLabel.removeAttr('class');
          $('#edit-field-news-media-contac > strong').removeAttr('class');
        }
      }
    });

    setTimeout(overrideNews, 1);
  })();

})(jQuery);
