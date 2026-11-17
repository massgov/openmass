const readiness = require('./readiness');

module.exports = async (page, scenario, viewport) => {
  console.log(`SCENARIO > ${scenario.label}: ${viewport.label}`);
  return readiness.guard(page, scenario, async step => {
    await step('navigation', () => readiness.navigation(page, scenario));
    // A navigation retry restores the DOM, so apply removals again before measuring.
    await page.evaluate(selectors => selectors.forEach(selector => {
      document.querySelectorAll(selector).forEach(element => element.remove());
    }), scenario.removeSelectors || []);
    await step('stylesheets', () => readiness.styles(page, scenario));

    await page.addStyleTag({
      content: `
        /* Disable animations. */
        *, *::before, *::after {
          animation-duration: 0s !important;
          transition-duration: 0s !important;
        }

        /* Mask random homepage image. */
        body.is-front .ma__search-banner {
          background: none !important;
        }

        /* Hide the focus-visible border around the mobile menu */
        .ma__header__hamburger__menu-button {
          outline: none !important;
        }

        /* Hide the sticky toc */
        #sticky-toc {
          display: none !important;
        }

        /* Keep vertical section navigation anchored during full-page capture. */
        .ma__sticky-nav {
          top: auto !important;
          bottom: -15px !important;
          position: absolute !important;
          z-index: 80;
        }

        /* Horizontal navigation belongs below its banner in the normal flow.
           Absolute positioning anchors it to the viewport and covers content.
           Preserve the component's top-state margins even after scroll JS runs. */
        .ma__sticky-nav.ma__sticky-nav--horizontal {
          position: relative !important;
          top: auto !important;
          bottom: auto !important;
          left: auto !important;
          margin-top: -20px !important;
          margin-bottom: 20px !important;
        }

        .ma__details__sticky-nav.stuck {
          position: static !important;
          top: auto !important;
          left: auto !important;
          width: auto !important;
          margin-top: -20px !important;
          z-index: auto !important;
        }

        .ma__org-page .pre-content {
          padding-top: 0 !important;
        }
      `,
    });

    await page.evaluate(async () => {
      // Undo the Google Optimize page-hiding snippet so we can access the page
      // before the 2s timeout. See https://developers.google.com/optimize.
      if (window.dataLayer && window.dataLayer.hide && window.dataLayer.hide.end) {
        window.dataLayer.hide.end();
      }

      // These masks cover changing homepage content. Other pages reuse the
      // same components and must retain their real content for comparison.
      if (!document.body.classList.contains('is-front')) return;

      // [FREQUENTLY CHANGING CONTENT] Replace random image credit on
      // Home page
      document.querySelectorAll('.ma__search-banner__image-name').forEach(function (e) {
        e.innerText = 'Good Picture';
      });
      document.querySelectorAll('.ma__search-banner__image-author').forEach(function (e) {
        e.innerText = 'John Smith';
      });

      // [FREQUENTLY CHANGING CONTENT] Replace link text of popular searches on
      // Home page
      document.querySelectorAll('.ma__search-banner__links .ma__link-list__item a').forEach(function (e) {
        e.innerText = 'Popular search query';
      });

      // [FREQUENTLY CHANGING CONTENT] Replace link text in Featured services on
      // Home page.
      document.querySelectorAll('.ma__stacked-row__section .ma__key-actions .ma__callout-link .ma__callout-link__container .ma__callout-link__text').forEach(function (e) {
        e.innerText = 'Featured service link text';
      });

      // [FREQUENTLY CHANGING CONTENT] Kill News & updates on Home page (show a
      // gray box instead)
      document.querySelectorAll('.ma__split-columns__column > .ma__rich-text').forEach(function (e) {
        e.innerHTML = '<article style="background-color: #888;"><span style="display: block; width: 100%; max-width: 100%; height: auto;">&nbsp;</span></article><h5>News title</h5>Teaser text';
      });
    });

    await step('lazy loading', () => readiness.scrollPage(page));
    await step('table of contents', () => readiness.tableOfContents(page));
    await step('video placeholders', () => readiness.videoPlayers(page));

    await step('embeds and page elements', async () => {
      // Wait for Caspio embeds to finish loading.
      await step('Caspio embeds', () => page.waitForFunction(() => document.querySelectorAll('.ma__caspio form').length === document.querySelectorAll('.ma__caspio').length));

      // Wait for Papa.parse in the csv_field module to complete.
      await step('CSV tables', () => page.waitForFunction(() => document.querySelectorAll('.csv-table').length === 0));

      // Wait for iFrame resizer.
      await step('iframe resizing', () => page.waitForFunction(() => [...document.querySelectorAll('.js-ma-responsive-iframe iframe[height=auto]')].every(iframe => iframe.dataset.backstopVideoState === 'placeholder')));

      switch (scenario.label) {
        case 'InfoDetails1':
        case 'InfoDetailsImageWrapLeft':
        case 'InfoDetailsImageWrapRight':
        case 'InfoDetailsImageNoWrapLeft':
        case 'InfoDetailsImageNoWrapRight':
        case 'InfoDetailsImageLeftAlign':
        case 'InfoDetailsImageRightAlign':
          // Scrolling to the inline feedback form hides the floating button.
          // Wait for its markup; visibility is settled after returning to top.
          await step('feedback button markup', () => page.locator('.ma__fixed-feedback-button').waitFor({ state: 'attached' }));
          break;
        case 'ServiceDetails':
          await step('service iframe button', () => page.frameLocator('.ma__iframe__container.js-ma-responsive-iframe iframe').first().locator('button').waitFor());
          break;
        case 'CampaignLandingHeaderSolidColor':
        case 'CampaignLandingHeaderVideo':
        case 'CampaignLandingHeaderBg':
          await page.addStyleTag({
            content: `
              @media (min-width: 768px) {
              .ma__card {
                  max-inline-size: none !important;
                  flex-flow: unset !important; /* Optional if flex-direction: row causes layout jitter */
                  max-width: 100% !important;
                  flex-direction: row !important;
                  flex-wrap: wrap !important;
                }
              }
        `,
          });

          break;
      }

      // Wait for any layout shift that nudges the footer.

      if (scenario.expectedStatus !== 404) {
        await step('footer', async () => {
          await page.locator('.ma__footer-new').waitFor();
          await page.locator('.ma__footer-new__navlinks').first().waitFor();
          await page.locator('.ma__footer-new__copyright').first().waitFor();
          await page.locator('.ma__footer-new__logo').first().waitFor();
          await page.locator('.ma__footer-new__container').waitFor();
        });
      }
    });
    // Footer interactions and lazy loading can leave the page scrolled, changing
    // banner parallax/crops. Always settle at the capture's starting position.
    await step('capture position', () => resetCapturePosition(page, viewport));
    const state = await step('images, fonts, Tableau and layout', () => readiness.assets(page, scenario));
    const iframeCount = await step('content iframes', () => readiness.contentIframeSnapshots(page));
    if (!state.tableaus.length && !iframeCount) return state;
    if (state.tableaus.length) await step('Tableau chart screenshots', () => readiness.tableauSnapshots(page));
    await step('final capture position', () => resetCapturePosition(page, viewport));
    return step('final layout', () => readiness.assets(page, scenario));
  });
};

async function resetCapturePosition(page, viewport) {
  await page.evaluate(() => window.scrollTo({ top: 0, left: 0, behavior: 'instant' }));
  await page.mouse.move(viewport.width - 1, 0);
  // Let scroll handlers run before resetting their active section. Mayflower
  // exposes its mobile breakpoint through ::before; keep the collapsed title
  // active at that breakpoint so clearing section links doesn't hide the label.
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(() => {
    document.querySelectorAll('.ma__sticky-nav').forEach(nav => {
      const mobile = getComputedStyle(nav, '::before').content.replace(/"/g, '') !== 'false';
      nav.querySelectorAll('.ma__sticky-nav__link').forEach(link => link.classList.toggle('is-active', mobile && link.classList.contains('is-title')));
    });
    resolve();
  }))));
}
