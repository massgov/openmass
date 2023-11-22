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

    if (selectedText === 'News' || selectedText === 'Press Statement') {
      $('#edit-field-news-body-0--description').text('Use this for an overview. You can add additional content using Sections below.');
    }
    else if (selectedText === 'Press Release') {
      $('#edit-field-news-body-0--description').text('Please enter the full press release text here. A “###” will be added automatically after this area.');
    }
    else if (selectedText === 'Speech') {
      $('#edit-field-news-body-0--description').text('');
    }

    $('.layout-node-form').find('label').each(function () {

      /**
     * Override the field_news_body label based on selected option of
     * field_news_type in News.
     */
      if ($(this).attr('for') === 'edit-field-news-body-0-value') {
        if (selectedText === 'News' || selectedText === 'Press Statement') {
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
      var horizontalTabLabel = $(this).find('strong');
      var tabLabel = horizontalTabLabel.text();
      if (tabLabel === 'Contacts') {
        if (selectedText === 'Press Release' || selectedText === 'Press Statement') {
          horizontalTabLabel.addClass('form-required');
          $('#edit-field-news-media-contac > strong').addClass('form-required');
        }
        else {
          horizontalTabLabel.removeAttr('class');
          $('#edit-field-news-media-contac > strong').removeAttr('class');
        }
      }
      if (tabLabel === 'Main Content') {
        if (selectedText === 'Press Release') {
          horizontalTabLabel.addClass('form-required');
          $('#edit-field-news-media-contac > strong').addClass('form-required');
        }
        else {
          horizontalTabLabel.removeAttr('class');
          $('#edit-field-news-media-contac > strong').removeAttr('class');
        }
      }
    });

    setTimeout(overrideNews, 1);
  })();

  Drupal.behaviors.alertsConditional = {
    attach: function (context) {
      $('.field--name-field-emergency-alert-link-type', context).each(function () {
        var $type = $('input', this);
        var $container = $(this).closest('.paragraphs-subform');
        var $link = $container.find('.field--name-field-emergency-alert-link');
        var $description = $container.find('.field--name-field-emergency-alert-content');
        var handler = function () {
          var val = $type.filter(':checked').val();
          switch (val) {
            case '0':
              $link.hide();
              $description.show();
              break;
            case '1':
              $link.show();
              $description.hide();
              break;
            case '2':
              $link.hide();
              $description.hide();
          }
        };

        $type.change(handler);
        handler();
      });
    }
  };

  /**
   * Tableau embed alignment field display.
   *
   * Hide or show the alignment field (field_tabl_alignment)
   * based on the display size field (field_tabl_display_size) value
   * in the Tableau embed paragraph.
   */
  Drupal.behaviors.tableauembedConditional = {
    attach: function (context) {
      $('.field--name-field-tabl-display-size', context).change(function () {
        if ($(this).find('option:selected').val() === 'x-large') {
          $(this).siblings('.field--name-field-tabl-alignment').hide().find('.fieldgroup').removeAttr('required');
          $(this).siblings('.field--name-field-tabl-wrapping').hide();
        }
        else {
          $(this).siblings('.field--name-field-tabl-alignment').show().find('.fieldgroup').attr('required', 'required');
          $(this).siblings('.field--name-field-tabl-wrapping').show();
        }
      }).change();
    }
  };

  Drupal.behaviors.imageParagraphConditional = {
    attach: function (context) {
      $('.field--name-field-image-display-size', context).change(function () {
        if ($(this).find('option:selected').val() === 'x-large') {
          $(this).siblings('.field--name-field-image-alignment').hide().find('.fieldgroup').removeAttr('required');
          $(this).siblings('.field--name-field-image-wrapping').hide();
        }
        else {
          $(this).siblings('.field--name-field-image-alignment').show().find('.fieldgroup').attr('required', 'required');
          $(this).siblings('.field--name-field-image-wrapping').show();
        }
      }).change();
    }
  };

  /**
   * Conditional fields on events.
   *
   * Make administrative area optional when unique option unchecked.
   */
  Drupal.behaviors.eventsConditional = {
    attach: function (context) {
      $('.field--name-field-event-address-type', context).change(function () {
        var addressTypeUniqueChecked = $('#edit-field-event-address-type-unique').prop('checked') !== false;
        $('.field--name-field-address-address').each(function () {
          $(this).find('.administrative-area').each(function () {
            if (!addressTypeUniqueChecked) {
              $(this).removeAttr('required');
            }
            else {
              $(this).attr('required', 'required');
            }
          });
        });
      }).change();
    }
  };

})(jQuery, Drupal);
