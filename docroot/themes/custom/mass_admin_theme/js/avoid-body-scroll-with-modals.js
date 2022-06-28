(function () {
  'use strict';

  function countsAsModal(element) {
    if (element) {
      var style = window.getComputedStyle(element);
      return (style.display !== 'none');
    }
    return false;
  }

  function controlBodyOverflow() {
    var showingModals =
      countsAsModal(document.querySelectorAll('[aria-describedby=drupal-modal]')[0]) ||
      countsAsModal(document.getElementById('drupal-modal'));
    document.getElementsByTagName('body')[0].setAttribute('data-showing-modal', showingModals);
  }

  var modalObserver = new MutationObserver(Drupal.debounce(controlBodyOverflow, 250));

  var config = {
    attributes: false,
    childList: true,
    characterData: false
  };

  modalObserver.observe(document.body, config);
})();
