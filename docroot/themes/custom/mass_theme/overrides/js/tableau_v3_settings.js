(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.tableauSettingsV3 = {
    attach: function (context, settings) {
      $('.ma_tableau_container', context).each(function () {
        const $item = $(this).find('.ma_tableau_item');

        // Only apply to connected_apps embeds.
        const tokenUrl = $item.data('token-url');
        if (!tokenUrl) {
          return;
        }

        const id = $item.attr('id');
        const vizUrl = $item.data('tableau-url');

        console.log(`[v3] Found v3 embed target: ${id}, URL: ${vizUrl}, Token URL: ${tokenUrl}`);

        if (!vizUrl) {
          console.error(`[v3] Missing Tableau URL for container ${id}`);
          return;
        }

        async function displayDashboard() {
          try {
            const response = await fetch(tokenUrl, { method: 'GET' });
            if (!response.ok) {
              throw new Error(`Token fetch failed: ${response.statusText}`);
            }

            const result = await response.json();
            console.log(`[v3] Token successfully fetched for ${id}`);

            const tableauViz = document.createElement('tableau-viz');
            tableauViz.setAttribute('src', vizUrl);
            tableauViz.setAttribute('toolbar', 'bottom');
            tableauViz.setAttribute('hide-tabs', '');

            if (result.token) {
              tableauViz.setAttribute('token', result.token);
            } else {
              console.warn('[v3] No token received from API');
            }

            const container = document.getElementById(id);
            container.innerHTML = '';
            container.appendChild(tableauViz);
          } catch (error) {
            console.error(`[v3] Failed to load Tableau Viz for ${id}:`, error);
          }
        }

        displayDashboard();
      });
    },
  };
})(jQuery, Drupal, drupalSettings);
