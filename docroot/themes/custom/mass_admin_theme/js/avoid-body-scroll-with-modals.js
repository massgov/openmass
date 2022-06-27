(function () {
  'use strict';

  function controlBodyOverflow() {
    var modals = (
      document.querySelectorAll('[aria-describedby=drupal-modal]').length +
      (document.getElementById('drupal-modal') ? 1 : 0)
    ) > 0;

    document.getElementsByTagName('body')[0].setAttribute('data-showing-modal', modals);
  }

  var modalObserver = new MutationObserver(Drupal.debounce(controlBodyOverflow, 250));

  var config = {
    attributes: false,
    childList: true,
    characterData: false
  };

  modalObserver.observe(document.body, config);
})();
