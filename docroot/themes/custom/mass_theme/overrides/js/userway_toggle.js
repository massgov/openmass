(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.userwayToggle = {
    attach: function (context) {
      const selector = '#panel-contrast .ma__button--secondary';
      once('userwayToggle', selector, context).forEach(function (button) {
        button.addEventListener('click', toggleUserWay);
      });

      // Load UserWay if enabled
      if (localStorage.getItem('userwayEnabled') === 'true') {
        loadUserWay();
      }
      updateButtonLabel();

      function toggleUserWay() {
        const isEnabled = localStorage.getItem('userwayEnabled') === 'true';
        localStorage.setItem('userwayEnabled', !isEnabled);
        if (!isEnabled) {
          loadUserWay();
        } else {
          unloadUserWay();
        }
        updateButtonLabel();
      }

      function loadUserWay() {
        const userwayScript = document.getElementById('userwayScript');
        if (!userwayScript) {
          const script = document.createElement('script');
          script.id = 'userwayScript';
          script.src = 'https://cdn.userway.org/widget.js';
          script.dataset.account = '8wSDGc4YEt';
          document.body.appendChild(script);
        }
      }

      function unloadUserWay() {
        // Reload the page to reset all changes made by the UserWay script
        window.location.reload();
      }

      function updateButtonLabel() {
        const isEnabled = localStorage.getItem('userwayEnabled') === 'true';
        const buttonText = isEnabled ? 'Disable Custom Styles' : 'Enable Custom Styles';
        document.querySelectorAll(selector).forEach(function (button) {
          button.textContent = buttonText;
        });
      }
    }
  };
})(Drupal, once);
