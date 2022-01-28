/**
 * @file
 */

var drupalLike = {behaviors: {massAlertBlocks: {}}};

var jQueryLike = function (elemOrSelector, context) {
  'use strict';

  var elem;

  if (typeof context == 'undefined') {
    context = document;
  }

  if (typeof elemOrSelector == 'string') {
    elem = document.querySelectorAll(elemOrSelector);
  }
  else {
    elem = elemOrSelector;
  }

  elem.data = function (key, value) {

    if (typeof value !== 'undefined') {
      elem.dataset[key] = value;
    }

    return typeof elem.dataset[key] !== 'undefined' ? elem.dataset[key] : null;
  };

  elem.insertBefore = function (selector) {
    elem.insertAdjacentHTML('beforebegin', jQueryLike(selector)[0].outerHTML);
  };

  // @see https://stackoverflow.com/a/4793630/1038565.
  function createElementFromHTML(htmlString) {
    var div = document.createElement('div');
    div.innerHTML = htmlString.trim();
    // Change this to div.childNodes to support multiple top-level nodes.
    return div.firstChild;
  }

  elem.insertAfter = function (selector) {
    var newNode = createElementFromHTML(elem.outerHTML);
    jQueryLike(selector)[0].parentNode.insertBefore(newNode, newNode.nextSibling);
    elem = newNode;
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

(function ($, drupalLike) {
  'use strict';
  drupalLike.behaviors.massAlertBlocks = {

    attach: function (context, nodeType) {

      $('.mass-alerts-block', context).each(function (i, e) {
        var $this = $(e);

        if ($this.data('alertProcessed')) {
          return;
        }

        $this.data('alertProcessed', 1);

        var path = $this.data('alertsPath');
        var removeContainer = false;

        if (!path.includes('alerts/sitewide')) {

          if (nodeType !== '') {
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

  // Attaching Mass Accordion Behavior.
  document.addEventListener('DOMContentLoaded', function () {
    Drupal.behaviors.MassAccordions.create(document.querySelector('.mass-alerts-block'));
  });

})(jQueryLike, drupalLike);
