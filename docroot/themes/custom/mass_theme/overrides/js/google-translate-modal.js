(function (Drupal, once, drupalSettings) {
  'use strict';

  function applyTranslations(wrapper, translations, selectedLanguage) {
    var fallback = translations.en || {};
    var language = translations[selectedLanguage] || fallback;
    var title = wrapper.querySelector('.ma__modal-title');
    var disclaimer = wrapper.querySelector('#ma__translate-help p');
    var translateButton = wrapper.querySelector('#ma__translate-apply');
    var revertButton = wrapper.querySelector('#ma__translate-reset');

    if (title && language.select_language) {
      title.textContent = language.select_language;
      title.lang = selectedLanguage;
    }

    if (disclaimer && language.disclaimer) {
      disclaimer.textContent = language.disclaimer;
      disclaimer.lang = selectedLanguage;
    }

    if (translateButton && language.translate_button) {
      translateButton.textContent = language.translate_button;
      translateButton.lang = selectedLanguage;
    }

    if (revertButton && language.revert_button) {
      revertButton.textContent = language.revert_button;
      revertButton.lang = selectedLanguage;
    }
  }

  Drupal.behaviors.massThemeGoogleTranslateModal = {
    attach: function (context) {
      var translations = drupalSettings.massTheme && drupalSettings.massTheme.googleTranslateLanguages;
      if (!translations) {
        return;
      }

      once('massThemeGoogleTranslateModal', '[data-utility-nav-modal="translate"]', context).forEach(function (wrapper) {
        var select = wrapper.querySelector('.ma__translate-select');

        if (!select) {
          return;
        }

        applyTranslations(wrapper, translations, select.value);

        select.addEventListener('change', function (event) {
          applyTranslations(wrapper, translations, event.target.value);
        });
      });
    }
  };
})(Drupal, once, drupalSettings);
