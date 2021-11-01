/* eslint-disable no-unused-vars */
function alerts(path, nodeType) {
  'use strict';

  var $this = document.querySelector('.mass-alerts-block');
  var removeContainer = false;

  function insertBefore(nodeA, nodeBselector) {
    var nodeB = document.querySelector(nodeBselector);
    document.getElementById(nodeA).insertAdjacentElement('beforebegin', nodeB);
  }

  function insertAfter(nodeA, nodeBselector) {
    var nodeB = document.querySelector(nodeBselector);
    document.getElementById(nodeA).insertAdjacentElement('afterend', nodeB);
  }

  if (path !== '/alerts/sitewide') {
    if (nodeType) {
      var positioned = false;

      if (nodeType === 'how_to_page') {
        if (document.querySelector('.mdocument.querySelector__page-header__optional-content') != null) {
          insertBefore($this, '.ma__page-header__optional-content');
          removeContainer = true;
          positioned = true;
        }
      }
      else if (nodeType === 'person') {
        if (document.querySelector('.ma__page-intro') != null) {
          insertAfter($this, '.ma__page-intro');
          removeContainer = true;
          positioned = true;
        }
      }

      if (!positioned) {

        if (document.querySelector('.ma__illustrated-header') != null) {
          insertAfter($this, '.ma__illustrated-header');
        }
        else if (document.querySelector('.ma__page-header') != null) {
          insertAfter($this, '.ma__page-header');
        }
        else if (document.querySelector('.ma__organization-navigation') != null) {
          insertAfter($this, '.ma__organization-navigation');
        }
        else if (document.querySelector('.ma__page-banner') != null) {
          insertAfter($this, '.ma__page-banner');
        }
        else if (document.querySelector('.pre-content') != null) {
          insertAfter($this, '.pre-content');
        }
      }
    }
    else {
      // Not a node page.
      path = false;
    }
  }

  if (path) {
    fetch(path).then(function (response) {
      return response.text();
    }).then(function (content) {
      if (!content) {
        $this.setAttribute('style', 'display: none');
        return;
      }
      $this.innerHTML = content;
      if (removeContainer) {
        $this.querySelector('.ma__page-banner__container').classList.remove('ma__page-banner__container');
      }
      // At the moment of fetch, we already have jQuery.
      jQuery(document).trigger('ma:AjaxPattern:Render', [{el: jQuery($this)}]);
    });
  }
}
