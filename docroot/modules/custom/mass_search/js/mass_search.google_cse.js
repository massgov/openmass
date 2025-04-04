/**
 * @file
 * Contains customizations for search forms on the site.
 */
(function () {
  'use strict';

  // ****** Mobile Search button should open mobile menu ******
  var mobileSearchButton = document.querySelector('.ma__header .ma__header__search .ma__header-search .ma__button-search');

  if (mobileSearchButton !== null) {
    mobileSearchButton.addEventListener('click', function (event) {
      if (document.documentElement.clientWidth <= 620) {
        event.preventDefault();
        document.querySelector('body').classList.toggle('show-menu');
      }
    });
  }

})(Drupal);

/**
 * Google CSE Autocompletion.
 *
 * This script uses a fake autocompleter to allow us to control the display more precisely,
 * and to lazy-load the actual CSE code.  Specifically, we define a class to handle interaction
 * with the autocomplete that uses the following workflow:
 *
 * When text is typed, require the CSE script.
 * When the CSE script has loaded, create a hidden CSE element.
 * Watch the CSE element for changes to the autocomplete list.
 * Invoke listeners when the autocomplete list changes.
 *
 *  Required Markup:
 *
 * <div class="ma__suggestions-container js-suggestions-container">
 <div class="ma__visually-hidden js-suggestions-help" role="status"  aria-live="polite"></div>
 <input
 id="..."
 class="..."
 autocomplete="off"
 data-suggest=""
 type="text" />
 <div class="ma__suggestions" data-suggestions=""></div>
 </div>
 */
(function ($) {
  'use strict';

  function debounce(fn, delay) {
    var timer = null;
    return function () {
      var context = this;
      var args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () {
        fn.apply(context, args);
      }, delay);
    };
  }

  // Workflow:

  // 1. Lazy require the cse script, then:
  // 2. Create a fake input element, then:
  // 3. Mirror anything typed into the real search box in the fake element, then:
  // 4. When autocomplete suggestions are returned, mirror them into the real autocomplete.

  function CSEAutocompleter(cx) {
    this.cx = cx;
    this.booted = false;
    this.fakeSearch = false;
    this.listeners = [];
  }
  CSEAutocompleter.prototype.requireCSE = function () {
    if (this.booted) {
      return this.booted;
    }
    var cx = this.cx;
    this.booted = new Promise(function (resolve, reject) {
      window.__gcse = {parsetags: 'explicit', callback: resolve};
      var gcse = document.createElement('script');
      gcse.type = 'text/javascript';
      gcse.async = true;
      gcse.src = 'https://cse.google.com/cse.js?cx=' + cx;
      var s = document.getElementsByTagName('script')[0];
      s.parentNode.insertBefore(gcse, s);
    });
    return this.booted;
  };
  CSEAutocompleter.prototype.createFakeSearch = function () {
    if (this.fakeSearch) {
      return this.fakeSearch;
    }
    var self = this;
    this.fakeSearch = new Promise(function (resolve, reject) {
      $('body').append('<div id="fake-search" style="display: none"><gcse:searchbox-only enableHistory="false" enableAutoComplete="true" gname="gcse"></gcse:searchbox-only></div>');
      google.search.cse.element.go('fake-search');

      // Use a Mutation Observer to watch the fake CSE element's autocomplete output.
      var output = $('.gssb_e');
      var mutationListener = debounce(function (e) {
        var suggestions = [].map.call($('.gssb_a .gsq_a', output), function (n) {
          return n.textContent.trim();
        });
        self.listeners.forEach(function (cb) {
          cb(suggestions);
        });
      }, 500);
      var observer = new MutationObserver(mutationListener);
      observer.observe(output[0], {childList: true, characterData: false, subtree: true});

      resolve($('#fake-search input.gsc-input'));
    });
    return this.fakeSearch;
  };
  CSEAutocompleter.prototype.update = function (text) {
    var self = this;
    this.requireCSE()
      .then(function () {return self.createFakeSearch();})
      .then(function (input) {
        input.val(text);
        // Trigger keyup to get autocomplete.
        var event = new Event('keydown');
        input[0].dispatchEvent(event);
      });
  };
  CSEAutocompleter.prototype.listen = function (cb) {
    this.listeners.push(cb);
  };

  var $input = $('[data-suggest]');
  var ac = new CSEAutocompleter('010551267445528504028:ivl9x2rf5e8');
  ac.listen(function (results) {
    $input.trigger('autocomplete:suggestionsUpdated', [results]);
  });
  $input.on('keyup', function (event) {
    if (event.key !== 'Enter' && event.key !== 'ArrowUp' && event.key !== 'ArrowDown') {
      ac.update(event.target.value);
    }
  });

  $input.on('autocomplete:suggestionsUpdated', function (e, suggestionList) {
    var input = $(this);
    var scope = input.parents('.js-suggestions-container');

    $('[role="listbox"]', scope).remove();
    $('[data-suggestions]', scope).html('<div role="listbox" id="suggestions-list"></div>');
    $('.js-suggestions-help', scope).empty();

    var $listbox = $('[role="listbox"]', scope);
    var value = input.val();
    var suggestions = [].filter.call(suggestionList.sort(), function (suggestionItem) {
      return !suggestionItem.indexOf(value);
    });

    if (value) {
      $.each(suggestions, function (k, v) {
        $listbox.append(
          '<div role="option" tabindex="-1" >' + v + '</div>'
        );
      });
      $listbox.find('[role="option"]', scope).each(function () {
        $(this).attr('id', input.attr('id') + '-' + $(this).index());
      });
      if (suggestions) {
        $('.js-suggestions-help', scope).text(
          'There are ' + suggestions.length + ' suggestions. Use the up and down arrows to browse.');
      }
    }

    $listbox.on('keydown', function (e) {

      if (e.key === 'Enter') {
        e.preventDefault();
        e.stopPropagation();
        input.data('realValue', input.val());
        input.focus();
        $listbox.remove();
        return;
      }

      if (e.key === 'Esc') {
        input.val(input.data('realValue'));
        input.focus();
        $listbox.hide();
        return;
      }

      var newOption;
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        newOption = $('.selected', scope).next();

        if (!newOption.length) {
          input.focus();
          $('.selected', scope).removeClass('selected');
          input.val(input.data('realValue'));
          return;
        }
      }

      if (e.key === 'ArrowUp') {
        e.preventDefault();
        newOption = $('.selected', scope).prev();

        if (!newOption.length) {
          input.focus();
          $('.selected', scope).removeClass('selected');
          input.val(input.data('realValue'));
          return;
        }
      }

      if (newOption && newOption.length) {
        $('.selected', scope).removeClass('selected');
        newOption.addClass('selected');
        $(this).attr('aria-activedescendant', newOption.attr('id'));
        input.val($('.selected', scope).text());
      }
    });

    $('[role="option"]', scope).on('click', function () {
      input.data('realValue', $(this).text());
      $('[data-suggest]', scope).val($(this).text())
        .focus();
      $listbox.remove();
    });

    $(document).on('mouseenter mouseleave', '[role=option]', function () {
      $(this).siblings().removeClass('selected');
      $(this).addClass('selected');
    });

    // To only attach events once.
    if (input.data('eventsAttached')) {
      return;
    }

    // Events below this line...
    input.on('keydown', function (e) {

      // Listbox is recreated when suggestions are updated,
      // hence we need to update the reference.
      $listbox = $('[role="listbox"]', scope);

      if (e.key === 'Escape') {

        if (input.val() !== '' && ($listbox.length === 0 || !$listbox.is(':visible'))) {
          input.val('');
          input.data('realValue', '');
        }

        e.preventDefault();
        input.focus();
        $listbox.hide();
        return;
      }

      if (e.key === 'ArrowUp') {
        e.preventDefault();
        $listbox.find('.selected', scope).removeClass('selected');
        $listbox.attr('tabindex', '0').focus();
        $listbox.attr('aria-activedescendant', $listbox.find('[role="option"]:last-child', scope).attr('id'));
        $listbox.find('[role="option"]:last-child', scope).addClass('selected');
        return;
      }

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        $listbox.find('.selected', scope).removeClass('selected');

        if (e.altKey) {
          $listbox.show();
        }
        else {
          $listbox.attr('tabindex', '0').focus();
          $listbox.find('.selected', scope).removeClass('selected');
          $listbox.attr('aria-activedescendant', $listbox.find('[role="option"]:first-child', scope).attr('id'));
          $listbox.find('[role="option"]:first-child', scope).addClass('selected');
          input.data('realValue', input.val());
          input.val($('.selected', scope).text());
        }
        return;
      }

      if (e.key === 'Tab') {
        $listbox.remove();
        return;
      }
    });

    input.data('eventsAttached', true);
  });

})(jQuery);
