(function() {
  'use strict';

  let hamburgerHeader, behaviorRun = false;

  Drupal.behaviors.microsites = {
    attach: function(context, settings) {
      hamburgerHeader = document.querySelector('.ma__header__hamburger__nav');

      if (hamburgerHeader && !behaviorRun) {
        behaviorRun = true;
        document.addEventListener('nav-wrap-change', function(event) {
          const {
            detail: {
              isWrapped
            }
          } = event;

          if (isWrapped) {
            hamburgerHeader.classList.add('force-mobile');
          } else {
            hamburgerHeader.classList.remove('force-mobile');
          }
        });
      }
    }
  };

})();
