'use strict';

(() => {
  function controlBodyOverflow() {
    const modals = document.querySelectorAll('[aria-describedby=drupal-modal]').length > 0;
    document.getElementsByTagName('body')[0].setAttribute('data-showing-modal', modals);
  }

  const modalObserver = new MutationObserver(Drupal.debounce(controlBodyOverflow, 250));

  const config = {
    attributes: false,
    childList: true,
    characterData: false
  };

  modalObserver.observe(document.body, config);
})();
