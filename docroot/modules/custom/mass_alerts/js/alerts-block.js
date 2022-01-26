/**
 * @file
 */

var drupalLike = {behaviors: {massAlertBlocks: {}}};

var jQueryLike = function (elemOrSelector, context) {
  'use strict';

  var elem;
  var $ = this;

  if (typeof context == 'undefined') {
    context = document;
  }

  if (typeof elemOrSelector == 'string') {
    elem = document.querySelectorAll(elemOrSelector);
  }
  else {
    elem = elemOrSelector;
  }

  elem.data = function (key) {
    return elem.dataset[key];
  };

  elem.insertBefore = function (selector) {
    elem.insertAdjacentHTML('beforebegin', $(selector)[0].outerHTML);
  };

  elem.insertAfter = function (selector) {
    elem.insertAdjacentHTML('afterend', $(selector)[0].outerHTML);
  };

  elem.hide = function () {
    elem[0].style.display = 'none';
    return elem;
  };

  elem.find = function (selector) {
    return elem.querySelector(selector);
  };

  elem.trigger = function (eventName, data) {
    var customEvent = new CustomEvent(eventName, data);
    document.dispatchEvent(customEvent);
  };

  elem.removeClass = function (classname) {
    elem[0].classList.remove(classname);
  };

  elem.html = function (htmlContent) {
    if (typeof htmlContent == 'undefined') {
      return elem.innerHTML;
    }
    else {
      elem.innerHTML = htmlContent;
    }
  };

  elem.each = function (fn) {

    elem.forEach(function (item, index) {

      fn(index, item);

    });
  };

  return elem;
};

(function ($, Drupal) {
  'use strict';
  Drupal.behaviors.massAlertBlocks = {

    /**
     * Drupal behavior.
     *
     * @param {HTMLDocument|HTMLElement} context
     * The context argument for Drupal.attachBehaviors()/detachBehaviors().
     * @param {object} settings
     * The settings argument for Drupal.attachBehaviors()/detachBehaviors().
     */
    attach: function (context, settings) {

      $('.mass-alerts-block', context).each(function (i, e) {
        var $this = $(e);
        var path = $this.data('alertsPath');
        var removeContainer = false;

        if (path !== '/alerts/sitewide') {

          if (settings.mass_alerts) {
            var nodeType = settings.mass_alerts.node.type;
            var positioned = false;

            if (nodeType === 'how_to_page') {
              if ($('.ma__page-header__optional-content').length) {
                $this.insertBefore('.ma__page-header__optional-content');
                removeContainer = true;
                positioned = true;
              }
            }
            else if (nodeType === 'person') {
              if ($('.ma__page-intro').length) {
                $this.insertAfter('.ma__page-intro');
                removeContainer = true;
                positioned = true;
              }
            }

            if (!positioned) {

              if ($('.ma__illustrated-header').length) {
                $this.insertAfter('.ma__illustrated-header');
              }
              else if ($('.ma__page-header').length) {
                $this.insertAfter('.ma__page-header');
              }
              else if ($('.ma__organization-navigation').length) {
                $this.insertAfter('.ma__organization-navigation');
              }
              else if ($('.ma__page-banner').length) {
                $this.insertAfter('.ma__page-banner');
              }
              else if ($('.pre-content').length) {
                $this.insertAfter('.pre-content');
              }
            }
          }
          else {
            // Not a node page.
            path = false;
          }
        }

        if (path) {
          var renderData = function (content) {
            if (!content) {
              $this.hide();
              return;
            }

            $this.html(content);
            if (removeContainer) {
              $this.find('.ma__page-banner__container').removeClass('ma__page-banner__container');
            }
            $(document).trigger('ma:AjaxPattern:Render', [{el: $this}]);
          };

          // Check if the data is already there.
          if (document.prefetchAlertsData[path]) {
            renderData(document.prefetchAlertsData[path]);
            document.prefetchAlertsData[path] = false;
          }
          else {
            // Data is not ready yet.
            // Lets listen mass_alerts_data_ready event.
            document.addEventListener('mass_alerts_data_ready', function () {
              if (document.prefetchAlertsData[path]) {
                renderData(document.prefetchAlertsData[path]);
                document.prefetchAlertsData[path] = false;
              }
            }, false);
          }
        }
      });
    }

  };
})(jQueryLike, drupalLike);
