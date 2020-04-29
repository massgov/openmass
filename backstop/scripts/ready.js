/**
 * Ready script, fires after pages have loaded, but before screenshots are captured.
 *
 * This script is used to hide or modify highly dynamic elements that may cause trouble
 * during visual regression testing.  If you are constantly seeing trivial failures for
 * an element, you can probably deal with it here.
 */
module.exports = async function(page, scenario, vp) {
    await page.addStyleTag({
        content: '' +
        // Force all animation to complete immediately.
        '*, *::before, *::after {\n' +
        '  animation-delay: 0ms !important;\n' +
        '  animation-duration: 0ms !important;\n' +
        '  transition-duration: 0ms !important;\n' +
        '  transition-delay: 0ms !important;\n' +
        '}' +
        // Kill Video embeds (show black box instead)
        '.fluid-width-video-wrapper:after {' +
        '  background: black;' +
        '  content: \'\';' +
        '  position: absolute;' +
        '  top: 0;' +
        '  left: 0;' +
        '  right: 0;' +
        '  bottom: 0;' +
        '  z-index: 100;' +
        '}' +
        // Kill iframes (show blue box instead)
        '.ma__iframe__container:after {' +
        '  background: #357B8F;' +
        '  content: \'\';' +
        '  position: absolute;' +
        '  top: 0;' +
        '  left: 0;' +
        '  right: 0;' +
        '  bottom: 0;' +
        '  z-index: 100;' +
        '}' +
        // Kill google Maps (show a green box instead)
        // .js-google-map for dynamic maps
        '.js-google-map {' +
        '  position: relative;' +
        '}' +
        '.js-google-map:before {' +
        '  background: #B2DEA2;\n' +
        '  content: \' \';\n' +
        '  display: block;\n' +
        '  position: absolute;\n' +
        '  top: 0;\n' +
        '  left: 0;\n' +
        '  right: 0;\n' +
        '  bottom: 0;\n' +
        '  z-index: 100;\n' +
        '}' +
        // Kill banner image on QAG campaign landing page (show a black box instead)
        '#ID1345331.ma__key-message--image, #imgID1345331 {' +
        '  position: relative;' +
        '}' +
        '#ID1345331.ma__key-message--image:before, #imgID1345331:before {' +
        '  background: #000000;\n' +
        '  content: \' \';\n' +
        '  display: block;\n' +
        '  position: absolute;\n' +
        '  top: 0;\n' +
        '  left: 0;\n' +
        '  right: 0;\n' +
        '  bottom: 0;\n' +
        '  z-index: 3;\n' +
        '}'
    });

    await page.evaluate(function (url) {
      if(!window.jQuery) {
        throw new Error(`jQuery was not found. This is usually caused by the server returning a 500 response. Please check ${url} in your browser.`);
      }
      // Disable jQuery animation for any future calls.
      jQuery.fx.off = true;
      // Immediately complete any in-progress animations.
      jQuery(':animated').finish();

      // Undo the Google Optimize page-hiding snippet so we can access the page
      // before the 2s timeout. See https://developers.google.com/optimize.
      if(window.dataLayer && window.dataLayer.hide && window.dataLayer.hide.end) {
        window.dataLayer.hide.end();
      }

      // Replace random image credit on homepage.
      document.querySelectorAll('.ma__search-banner__image-name').forEach(function(e) {
        e.innerText = 'Good Picture';
      });
      document.querySelectorAll('.ma__search-banner__image-author').forEach(function(e) {
        e.innerText = 'John Smith';
      });
    }, scenario.url);

    // Finally, wait for ajax to complete - this is to give alerts
    // time to finish rendering. This can take a while, especially
    // in local environments.
    await page.waitForFunction('jQuery.active == 0');

    // Wait for all visible fonts to complete loading.
    await page.evaluate(async function() {
      await document.fonts.ready;
    })

    // We can add a slight delay here. This can cover up jitter caused
    // by weird network conditions, slow JS, etc, but if we need an extra
    // delay after page load, it probably indicates there's a problem with
    // performance.
    // await page.waitFor(2000);
}
