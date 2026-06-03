(function (Drupal, once, drupalSettings) {
  'use strict';

  /**
   * Same root Mayflower uses when portaling the translate modal on mobile.
   * Keeping the Google mount here avoids orphaned widget nodes after translate.
   */
  var STABLE_MOUNT_ROOT = document.documentElement;

  function isGoogleTranslateInitialized() {
    var mount = document.getElementById('google_translate_element');
    return Boolean(
      mount && (
        mount.classList.contains('has-rendered') ||
        mount.querySelector('.goog-te-gadget') ||
        document.querySelector('iframe.goog-te-banner-frame')
      )
    );
  }

  function findSiblingGoogTeWrapper(wrapper) {
    var children = wrapper.children;
    for (var i = 0; i < children.length; i++) {
      if (children[i].classList && children[i].classList.contains('ma__goog-te-wrapper')) {
        return children[i];
      }
    }
    return null;
  }

  function isLiveGoogTeWrapper(node) {
    if (!node) {
      return false;
    }
    var mount = node.querySelector('#google_translate_element');
    return Boolean(
      mount && (
        mount.classList.contains('has-rendered') ||
        mount.querySelector('.goog-te-gadget')
      )
    );
  }

  function ensureGoogleTranslateMountOutsideOverlay(wrapper) {
    var overlay = wrapper.querySelector('.ma__modal-overlay');
    if (!overlay) {
      return;
    }

    var innerWrapper = overlay.querySelector('.ma__goog-te-wrapper');
    var outerWrapper = findSiblingGoogTeWrapper(wrapper);
    var initialized = isGoogleTranslateInitialized();

    if (innerWrapper && outerWrapper && innerWrapper !== outerWrapper) {
      if (!isLiveGoogTeWrapper(innerWrapper)) {
        innerWrapper.remove();
      }
      innerWrapper = overlay.querySelector('.ma__goog-te-wrapper');
    }

    var activeWrapper = outerWrapper || innerWrapper;
    if (!activeWrapper) {
      return;
    }

    if (initialized) {
      if (overlay.contains(activeWrapper)) {
        STABLE_MOUNT_ROOT.appendChild(activeWrapper);
      }
      return;
    }

    if (innerWrapper && !outerWrapper) {
      wrapper.appendChild(innerWrapper);
      activeWrapper = innerWrapper;
    }

    if (activeWrapper.parentElement !== STABLE_MOUNT_ROOT) {
      STABLE_MOUNT_ROOT.appendChild(activeWrapper);
    }

    innerWrapper = overlay.querySelector('.ma__goog-te-wrapper');
    if (innerWrapper && innerWrapper !== activeWrapper && !isLiveGoogTeWrapper(innerWrapper)) {
      innerWrapper.remove();
    }
  }

  function relocateAllTranslateMounts(root) {
    var scope = root || document;
    scope.querySelectorAll('[data-utility-nav-modal="translate"]').forEach(function (wrapper) {
      ensureGoogleTranslateMountOutsideOverlay(wrapper);
    });
  }

  function isTranslateUiClick(target) {
    if (!target || !target.closest) {
      return false;
    }
    return Boolean(
      target.closest('[data-utility-nav-modal="translate"]') ||
      target.closest('.ma__modal--translate.ma__active') ||
      target.closest('.ma__goog-te-wrapper')
    );
  }

  function bindTranslateClickIsolation() {
    if (document.documentElement.dataset.massThemeTranslateClickGuard) {
      return;
    }
    document.documentElement.dataset.massThemeTranslateClickGuard = 'true';

    // Google attaches a body bubble listener; stop translate UI clicks from reaching it.
    document.addEventListener('click', function (event) {
      if (isTranslateUiClick(event.target)) {
        event.stopPropagation();
      }
    }, false);
  }

  function bindTranslateModalOpenGuards() {
    if (document.documentElement.dataset.massThemeTranslateGuards) {
      return;
    }
    document.documentElement.dataset.massThemeTranslateGuards = 'true';

    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-modal-trigger]');
      if (!trigger) {
        return;
      }
      var wrapper = trigger.closest('[data-utility-nav-modal="translate"]');
      if (!wrapper) {
        return;
      }
      ensureGoogleTranslateMountOutsideOverlay(wrapper);
    }, true);
  }

  function applyTranslations(wrapper, translations, selectedLanguage) {
    var fallback = translations.en || {};
    var language = translations[selectedLanguage] || fallback;
    var disclaimer = wrapper.querySelector('#ma__translate-help p');
    var translateAction = wrapper.querySelector('#ma__translate-apply');
    var showOriginalAction = wrapper.querySelector('#ma__translate-reset');

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

  window.massThemeRelocateGoogleTranslateMount = relocateAllTranslateMounts;
  window.massThemeBindTranslateClickIsolation = bindTranslateClickIsolation;

  Drupal.behaviors.massThemeGoogleTranslateModal = {
    attach: function (context) {
      relocateAllTranslateMounts(context);
      bindTranslateClickIsolation();
      bindTranslateModalOpenGuards();

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
