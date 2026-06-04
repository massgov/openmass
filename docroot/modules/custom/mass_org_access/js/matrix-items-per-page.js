/**
 * @file
 * Reloads the org mapping matrix when the "items per page" select changes.
 */

((Drupal, once) => {
  Drupal.behaviors.oogItemsPerPage = {
    attach(context) {
      once('oog-items-per-page', '[data-oog-items-per-page]', context).forEach(
        (select) => {
          select.addEventListener('change', () => {
            const url = new URL(window.location.href);
            url.searchParams.set('items', select.value);
            // Reset to the first page when the page size changes.
            url.searchParams.delete('page');
            window.location.href = url.toString();
          });
        },
      );
    },
  };
})(Drupal, once);
