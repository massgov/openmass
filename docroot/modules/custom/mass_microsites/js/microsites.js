(function (once) {
  'use strict';

  Drupal.behaviors.microsites = {
    attach: function (context) {
      var hamburgerHeader = once('microsite-header', '.ma__header__hamburger__nav', context)[0];

      if (hamburgerHeader) {
        document.addEventListener('nav-wrap-change', function (event) {
          var isWrapped = event.detail.isWrapped;

          if (isWrapped) {
            hamburgerHeader.classList.add('force-mobile');
          }
          else {
            hamburgerHeader.classList.remove('force-mobile');
          }
        });
      }
    }
  };

})(once);
