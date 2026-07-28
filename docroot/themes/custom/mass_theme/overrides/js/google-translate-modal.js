(function (Drupal, drupalSettings) {
  'use strict';

  function applyTranslations(container, translations, selectedLanguage) {
    var fallback = translations.en || {};
    var language = translations[selectedLanguage] || fallback;
    var disclaimer = container.querySelector('#ma__translate-help p');
    var translateAction = container.querySelector('#ma__translate-apply');
    var showOriginalAction = container.querySelector('#ma__translate-reset');

    if (disclaimer && language.disclaimer) {
      disclaimer.textContent = language.disclaimer;
      disclaimer.lang = selectedLanguage;
    }

    if (translateAction && language.translate_action) {
      translateAction.textContent = language.translate_action;
      translateAction.lang = selectedLanguage;
    }

    if (showOriginalAction && language.show_original_action) {
      showOriginalAction.textContent = language.show_original_action;
      showOriginalAction.lang = selectedLanguage;
    }
  }

  function applyTranslationsFromSelect(select, translations) {
    var container = select.closest('.ma__translate-container');

    if (container) {
      applyTranslations(container, translations, select.value);
    }
  }

  Drupal.behaviors.massThemeGoogleTranslateModal = {
    attach: function (context) {
      var translations = drupalSettings.massTheme && drupalSettings.massTheme.googleTranslateLanguages;
      if (!translations) {
        return;
      }

      var selects = Array.prototype.slice.call(context.querySelectorAll('.ma__translate-select'));

      if (context.matches && context.matches('.ma__translate-select')) {
        selects.unshift(context);
      }

      selects.forEach(function (select) {
        applyTranslationsFromSelect(select, translations);
      });

      // Drupal behaviors can attach repeatedly; only bind the delegated listener once.
      if (!document.documentElement.dataset.massThemeGoogleTranslateModalListener) {
        document.documentElement.dataset.massThemeGoogleTranslateModalListener = 'true';

        document.addEventListener('change', function (event) {
          if (event.target.matches('.ma__translate-select')) {
            applyTranslationsFromSelect(event.target, translations);
          }
        });
      }
    }
  };
})(Drupal, drupalSettings);
