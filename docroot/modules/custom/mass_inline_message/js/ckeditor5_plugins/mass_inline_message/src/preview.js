/**
 * @file
 * Loads server-rendered Mayflower inline-message markup for CKEditor widgets.
 *
 * Preview uses a same-origin POST with the editor session cookie. Access is
 * checked on the server (use text format permission). No extra CSRF header is
 * used here, same as other CKEditor dialog routes in this project.
 */

const previewRequests = new WeakMap();

/**
 * Renders Message box preview HTML via the server theme pipeline.
 */
export function renderMayflowerPreview(domElement, config, { title, type, body }) {
  if (!config?.previewUrl) {
    domElement.innerHTML = `<p>${Drupal.t('Preview unavailable.')}</p>`;
    return;
  }

  const requestKey = `${title}|${type}|${body}`;
  const pending = previewRequests.get(domElement);
  if (pending?.key === requestKey) {
    return;
  }

  domElement.classList.add('mass-inline-message-ckeditor-widget__preview');
  domElement.innerHTML = `<div class="mass-inline-message-ckeditor-widget__loading">${Drupal.t('Loading preview…')}</div>`;

  const controller = new AbortController();
  previewRequests.set(domElement, { key: requestKey, controller });

  fetch(config.previewUrl, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({
      title,
      type,
      body,
    }),
    signal: controller.signal,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error('Preview request failed');
      }
      return response.json();
    })
    .then((data) => {
      if (previewRequests.get(domElement)?.key !== requestKey) {
        return;
      }
      domElement.innerHTML = data.html || '';
    })
    .catch(() => {
      if (previewRequests.get(domElement)?.key !== requestKey) {
        return;
      }
      domElement.innerHTML = `<p class="mass-inline-message-ckeditor-widget__error">${Drupal.t('Preview failed to load.')}</p>`;
    });
}
