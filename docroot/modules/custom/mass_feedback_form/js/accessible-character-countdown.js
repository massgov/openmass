'use strict';

// Accessible Character Counter Plugin For Text Field
// https://www.jqueryscript.net/form/Accessible-Character-Counter-jQuery.html
(function ($) {
  $.fn.accessibleCharCount = function (options) {
    'use strict';

    var settings = $.extend(true, {}, $.fn.accessibleCharCount.defaults, options);

    // Supply default text as necessary
    // Note: we can't include this in the defaults object because
    // we don't want this text to be merged with user-provided values
    if (!('en' in settings.beforeNumberElem.html)) {
      settings.beforeNumberElem.html['en'] = [''];
    }
    else if (typeof settings.beforeNumberElem.html.en === 'string') {
      settings.beforeNumberElem.html['en'] = [settings.beforeNumberElem.html.en];
    }

    if (!('fr' in settings.beforeNumberElem.html)) {
      settings.beforeNumberElem.html['fr'] = [''];
    }
    else if (typeof settings.beforeNumberElem.html.fr === 'string') {
      settings.beforeNumberElem.html['fr'] = [settings.beforeNumberElem.html.fr];
    }

    if (!('en' in settings.afterNumberElem.html)) {
      settings.afterNumberElem.html['en'] = [' characters remaining', ' character remaining', ' characters remaining'];
    }
    else if (typeof settings.afterNumberElem.html.en === 'string') {
      settings.afterNumberElem.html['en'] = [settings.afterNumberElem.html.en];
    }

    if (!('fr' in settings.afterNumberElem.html)) {
      settings.afterNumberElem.html['fr'] = [' caractères restants', ' caractère restant', ' caractères restants'];
    }
    else if (typeof settings.afterNumberElem.html.fr === 'string') {
      settings.afterNumberElem.html['fr'] = [settings.afterNumberElem.html.fr];
    }

    function getRandomInt(max) {
      return Math.floor(Math.random() * max);
    }

    function countRemaining(el) {
      var messageText = el.value;
      var messageLength = messageText.length;

      var maxLength = el.getAttribute('maxlength');
      return maxLength - messageLength;
    }

    function provideNewHtml(settings, remaining) {
      var returnObj = {beforeNumberElem: '',
        numberElem: '',
        afterNumberElem: ''
      };

      var pageLanguage = document.documentElement.getAttribute('lang');
      if (!pageLanguage) {
        // Default to English
        pageLanguage = 'en';
      }

      // beforeNumberElem
      if (pageLanguage in settings.beforeNumberElem.html) {
        var beforeNumberElemArray = settings.beforeNumberElem.html[pageLanguage];
        if (remaining >= beforeNumberElemArray.length) {
          // Generic plural form; use last element in array.
          returnObj.beforeNumberElem = beforeNumberElemArray[beforeNumberElemArray.length - 1];
        }
        else {
          returnObj.beforeNumberElem = beforeNumberElemArray[remaining];
        }
      }
      else {
        // If no text is available for current language then show no text
        returnObj.beforeNumberElem = '';
      }

      // numberElem
      returnObj.numberElem = remaining;

      // afterNumberElem
      if (pageLanguage in settings.afterNumberElem.html) {
        var afterNumberElemArray = settings.afterNumberElem.html[pageLanguage];
        if (remaining >= afterNumberElemArray.length) {
          // Generic plural form; use last element in array
          var lastIndex = afterNumberElemArray.length - 1;
          returnObj.afterNumberElem = afterNumberElemArray[lastIndex];
        }
        else {
          returnObj.afterNumberElem = afterNumberElemArray[remaining];
        }
      }
      else {
        // If no text is available for current language then show no text
        returnObj.afterNumberElem = '';
      }

      return returnObj;
    }

    return this.each(function () {
      // Make sure the element accepts typed input
      if (this.value === undefined) {
        console.log('Unable to attach a character counter to the following element:');
        console.log(this);
        return false;
      }

      // Use maxlength attribute wherever possible
      var maxLength = $(this).attr('maxlength');
      if (!isNaN(maxLength)) {
        // The maxlength attribute exists; use it
        settings['maxLength'] = maxLength;
      }
      else {
        // The maxlength attribute does not exist; set it
        $(this).attr('maxlength', settings.maxLength);
      }

      // Create and attach elements
      var remaining = countRemaining(this);
      var newHtml = provideNewHtml(settings, remaining);
      var idSuffix = getRandomInt(9999); // Avoid collisions

      var $beforeNumberElem = $('<span></span>').attr(settings.beforeNumberElem.attrs).css(settings.beforeNumberElem.css).html(newHtml.beforeNumberElem);

      var numberElemID = settings.numberElem.idPrefix + idSuffix;
      var $numberElem = $('<span></span>', {id: numberElemID}).attr(settings.numberElem.attrs).css(settings.numberElem.css).html(newHtml.numberElem);

      var $afterNumberElem = $('<span></span>').attr(settings.afterNumberElem.attrs).css(settings.afterNumberElem.css).html(newHtml.afterNumberElem);

      var wrapperElemID = settings.wrapperElem.idPrefix + idSuffix;
      var wrapperElemInitialState = 'polite';
      var $wrapperElem = $('<div></div>', {id: wrapperElemID}).attr(settings.wrapperElem.attrs).attr('aria-live', wrapperElemInitialState).css(settings.wrapperElem.css).append($beforeNumberElem).append($numberElem).append($afterNumberElem).insertAfter($(this));
      $(this).attr('aria-describedby', wrapperElemID);

      // Attach input listener
      var oldHtml = {beforeNumberElem: newHtml.beforeNumberElem,
        numberElem: newHtml.numberElem,
        afterNumberElem: newHtml.afterNumberElem
      };
      function update(el) {
        var remaining = countRemaining(el);
        var newHtml = provideNewHtml(settings, remaining);
        var status;

        if (newHtml.beforeNumberElem !== oldHtml.beforeNumberElem) {
          $beforeNumberElem.html(newHtml.beforeNumberElem);
          oldHtml.beforeNumberElem = newHtml.beforeNumberElem;
        }

        if (newHtml.numberElem !== oldHtml.numberElem) {
          $numberElem.html(newHtml.numberElem);
          oldHtml.numberElem = newHtml.numberElem;
        }

        if (newHtml.afterNumberElem !== oldHtml.afterNumberElem) {
          $afterNumberElem.html(newHtml.afterNumberElem);
          oldHtml.afterNumberElem = newHtml.afterNumberElem;
        }

        if (remaining === settings['maxLength']) {
          status = 'polite';
          // Announce the whole string (e.g. '20 characters remaining')
          $wrapperElem.attr('aria-live', 'polite');
          $wrapperElem.attr('aria-atomic', true);
        }
        else if (remaining > settings.triggerPolite) {
          status = 'off';
          // Don't announce anything
          $wrapperElem.attr('aria-live', 'off');
          $wrapperElem.attr('aria-atomic', true);
        }
        else if (remaining > settings.triggerAssertive) {
          status = 'polite';
          // Announce only the number (e.g. '50')
          $wrapperElem.attr('aria-live', 'polite');
          if (settings.everyNth > 0) {
            if (remaining % settings.everyNth === 0) {
              $wrapperElem.attr('aria-atomic', true);
            }
            else {
              $wrapperElem.attr('aria-atomic', false);
            }
          }
        }
        else {
          status = 'assertive';
          // Announce the whole string (e.g. '20 characters remaining')
          $wrapperElem.attr('aria-live', 'assertive');
          $wrapperElem.attr('aria-atomic', true);
          if (remaining === 0) {settings.atMaxLength.call(el);}
        }
        return {remaining: remaining, status: status};
      }

      // Attach keyup listener
      $(this).on('keyup', function (e) {
        settings.beforeUpdate.call(this);
        var updateObj = update(this);
        settings.afterUpdate.call(this, updateObj.remaining, updateObj.status);
      });

      // Attach document language listener
      var currentElement = this;
      var observer = new window.MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
          if (mutation.type === 'attributes' && mutation.attributeName === 'lang') {update(currentElement);}
        });
      });
      observer.observe(document.documentElement, {attributes: true});
    });
  };

  $.fn.accessibleCharCount.defaults = {maxLength: 160,
    // The maximum length of the element
    // Will be overridden by actual maxlength if present
    triggerPolite: 50,
    // The number of remaining characters that triggers the polite announcement
    triggerAssertive: 20,
    // The number of remaining characters that triggers the assertive announcement
    everyNth: 5,
    // How frequently to speak the whole message, as opposed to just the number
    // Set to 0 to turn off this feature
    wrapperElem: {idPrefix: 'accessibleCharCount-wrapper-',
      // Prefix to apply to the ID
      attrs: {class: 'accessibleCharCount-wrapperElem'},
      css: {}
      // Inline CSS
    },
    beforeNumberElem: {attrs: {class: 'accessibleCharCount-beforeNumberElem'},
      css: {},
      html: {}
      // Contents, by language and number
      // Defaults are provided in code
    },
    numberElem: {idPrefix: 'accessibleCharCount-number-',
      // Prefix to apply to the ID
      attrs: {class: 'accessibleCharCount-numberElem'},
      css: {}
    },
    afterNumberElem: {attrs: {class: 'accessibleCharCount-afterNumberElem visually-hidden'},
      css: {},
      html: {}
    },
    beforeUpdate: function beforeUpdate() {},
    // Callback function before processing
    afterUpdate: function afterUpdate() {},
    // Callback function after processing
    atMaxLength: function atMaxLength() {}
    // Callback function when the maximum length is reached
  };
})(jQuery);
