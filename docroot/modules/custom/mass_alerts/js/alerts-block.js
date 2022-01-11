/* eslint-disable no-unused-vars */
function alerts(path, nodeType, $alertsBlock) {
  'use strict';
  var removeContainer = false;
  var positioned = false;
  var alertPositionInterval = null;

  function insertBefore(nodeA, nodeBselector) {
    var nodeB = document.querySelector(nodeBselector);
    document.getElementById(nodeB).insertAdjacentElement('beforebegin', nodeA);
  }

  function insertAfter(nodeA, nodeBselector) {
    var nodeB = document.querySelector(nodeBselector);
    nodeB.insertAdjacentElement('afterend', nodeA);
  }

  function setPositionByNodeType() {
    if (nodeType === 'how_to_page') {
      if (document.querySelector('.mdocument.querySelector__page-header__optional-content') != null) {
        insertBefore($alertsBlock, '.ma__page-header__optional-content');
        removeContainer = true;
        positioned = true;
      }
    }
    else if (nodeType === 'person') {
      if (document.querySelector('.ma__page-intro') != null) {
        insertAfter($alertsBlock, '.ma__page-intro');
        removeContainer = true;
        positioned = true;
      }
    }
  }

  function positionAlert() {
    if (!nodeType) {
      path = false;
      return;
    }

    setPositionByNodeType();

    if (positioned) {
      $alertsBlock.setAttribute('style', null);
      return;
    }

    var areasToMoveAlerts = [
      '.ma__illustrated-header',
      '.ma__page-header',
      '.ma__organization-navigation',
      '.ma__page-banner',
      '.pre-content'
    ];

    areasToMoveAlerts.forEach(function (areaSelector) {
      if (!positioned && document.querySelector(areaSelector) === null) {
        return;
      }
      insertAfter($alertsBlock, areaSelector);
      positioned = true;
    });

    if (positioned) {
      $alertsBlock.setAttribute('style', null);
      return;
    }
  }

  var alertPositionIntervalMs = 100;

  if (path !== '/alerts/sitewide') {
    $alertsBlock.setAttribute('style', 'display: none');

    // This is faster than listen DOMContentLoaded.
    alertPositionInterval = setInterval(function () {
      positionAlert();
      if (positioned) {
        clearInterval(alertPositionInterval);
      }
    }, alertPositionIntervalMs);

    // Avoid interval to run forever, in case the logic fails.
    document.addEventListener('DOMContentLoaded', function () {
      setTimeout(function () { clearInterval(alertPositionInterval); }, alertPositionIntervalMs);
    });
  }

  function processData(content) {
    if (!content) {
      $alertsBlock.setAttribute('style', 'display: none');
      return;
    }
    $alertsBlock.innerHTML = content;
    if (removeContainer) {
      $alertsBlock.querySelector('.ma__page-banner__container').classList.remove('ma__page-banner__container');
    }
    var event = new Event('ma:AjaxPattern:Render');
    event.el = $alertsBlock;
    document.dispatchEvent(event);
  }

  function checkData() {
    if (typeof document.prefetchAlertsData[path] === 'undefined') {
      return false;
    }
    processData(document.prefetchAlertsData[path]);
    return true;
  }

  if (typeof document.prefetchAlertsData === 'undefined') {
    return;
  }

  if (!checkData()) {
    document.addEventListener('mass_alerts_data_ready', checkData, false);
  }

}
