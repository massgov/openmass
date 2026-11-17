// Installed before Drupal scripts, only for the Homepage scenario. Leave the
// site's renderer in charge of responsive images, styling, and image credits.
module.exports = function selectHomepageBanner() {
  if (window !== window.top) return;
  window.__backstopHomepageBanner = { state: 'waiting' };
  let settings;
  Object.defineProperty(window, 'drupalSettings', {
    configurable: true,
    enumerable: true,
    get: () => settings,
    set(value) {
      settings = value;
      if (!Array.isArray(value?.homepage_background_images)) return;
      try {
        const images = value.homepage_background_images.map(image => {
          if (!image.image_url?.trim() || !image.image_url_mobile?.trim()) {
            throw new Error('Homepage banner is missing a desktop or mobile image URL');
          }
          return { image, path: new URL(image.image_url, location.href).pathname };
        }).sort((left, right) => left.path.localeCompare(right.path, 'en'));
        if (!images.length) throw new Error('No homepage banner images are configured');
        const { image, path } = images[0];
        settings.homepage_background_images = [image];
        window.__backstopHomepageBanner = {
          state: 'selected', path, source: image.image_url, mobileSource: image.image_url_mobile
        };
      } catch (error) {
        window.__backstopHomepageBanner = { state: 'error', error: error.message };
      }
    }
  });
};
