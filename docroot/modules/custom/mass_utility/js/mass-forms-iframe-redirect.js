/**
 * @file
 * Provide connection between forms.mass.gov and mass.gov for redirections after
 * form submit.
 */

(function (Drupal) {

  'use strict';

  Drupal.behaviors.massFormsIframeRedirect = {
    attach: function (context, settings) {
      window.addEventListener('message', function(event) {
        const iframe = document.querySelector(".js-iframe-resizer");

        if (iframe) {
          const srcUrl = iframe.getAttribute("src");
          const urlObject = new URL(srcUrl);
          // Extract the domain (protocol + hostname)
          const domain = urlObject.origin;
          // Ensure the message is from the trusted origin.
          // We validate trusted domains in PHP code in mass_validation_entity_bundle_field_info_alter().
          if (event.origin === domain) {
            const data = event.data;
            // Check if the message contains a redirect action
            if (data.action === 'redirect' && data.url) {
              // Redirect to the specified URL.
              window.location.href = data.url;
            }
          }
        }

      });
    }
  };

})(Drupal);
