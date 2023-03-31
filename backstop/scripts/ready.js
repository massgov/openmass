module.exports = async (page, scenario, viewport) => {
  console.log(`SCENARIO > ${scenario.label}: ${viewport.label}`);

  // Disable animations.
  await page.addStyleTag({
    content: `
      *, *::before, *::after {
        animation-duration: 0s !important;
        transition-duration: 0s !important;
      }
    `,
  });

  // Mask random homepage image.
  await page.addStyleTag({
    content: `
      body.is-front .ma__search-banner {
        background: none !important;
      }
    `,
  });

  // Temporarily hide the feedback button and table of contents
  await page.addStyleTag({
    content: `
      .ma__fixed-feedback-button, .ma__sticky-toc stuck {
        display: none !important;
      }
    `,
  });

  await page.evaluate(async () => {
    // Undo the Google Optimize page-hiding snippet so we can access the page
    // before the 2s timeout. See https://developers.google.com/optimize.
    if (window.dataLayer && window.dataLayer.hide && window.dataLayer.hide.end) {
      window.dataLayer.hide.end();
    }

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

    // [FREQUENTLY CHANGING CONTENT] Replace Updates From The Baker-Polito
    // Administration item title on governor's page.
    document.querySelectorAll('.ma__featured-item__title-container .ma__featured-item__title span').forEach(function (e) {
      e.innerText = 'Featured item title';
    });

    // [FREQUENTLY CHANGING CONTENT] Replace teaser content in Recent news &
    // announcements item on governor's page.
    document.querySelectorAll('.ma__press-listing__secondary-item .ma__press-teaser__details').forEach(function (e) {
      e.innerText = 'Press teaser details';
    });
  });

  // Wait for Caspio embeds to finish loading.
  await page.waitForFunction(() => document.querySelectorAll('.ma__caspio form').length === document.querySelectorAll('.ma__caspio').length);

  // Wait for Papa.parse in the csv_field module to complete.
  await page.waitForFunction(() => document.querySelectorAll('.csv-table').length === 0);

  // Wait for leaflet map to load.
  const hasMap = await page.locator('.ma__leaflet-map').is_visible;
  if (hasMap) {
    // Wait for all image tiles to load.
    await page.waitForFunction(() => Array.from(document.querySelectorAll('img.leaflet-tile')).filter(img => !img.complete).length === 0);
    // Wait for all markers to load.
    await page.waitForFunction(() => Array.from(document.querySelectorAll('img.leaflet-marker-icon.leaflet-interactive')).filter(img => !img.complete).length === 0);
    // Force checks - see https://playwright.dev/docs/actionability
    await page.locator('.ma__leaflet-map__map .leaflet-pane').hover();
    await page.locator('.ma__leaflet-map__map .leaflet-control-zoom').hover();
  }

  // Wait for iFrame resizer.
  await page.waitForFunction(() => document.querySelectorAll('.js-ma-responsive-iframe iframe[height=auto]').length === 0);

  switch (scenario.label) {
    case 'InfoDetails1':
      await page.waitForSelector('.cbFormErrorMarker');
      break;
    case 'InfoDetailsImageWrapLeft':
    case 'InfoDetailsImageWrapRight':
    case 'InfoDetailsImageNoWrapLeft':
    case 'InfoDetailsImageNoWrapRight':
    case 'InfoDetailsImageLeftAlign':
    case 'InfoDetailsImageRightAlign':
      await page.locator('.ma__fixed-feedback-button');
      break;
    case 'ExpansionOfAccordions1':
      await page.evaluate(() => document.querySelector('.ma__sticky-nav').setAttribute('data-sticky', 'bottom'));
      await page.locator('.ma__sticky-nav');
      break;
    case 'OrgElectedOfficial':
      await page.locator('.ma__organization-navigation').waitFor();
      break;
  }


  // Wait for any layout shift that nudges the footer.
  if (scenario.label !== '404') {
    await page.waitForSelector('.ma__footer-new');
    await page.locator('.ma__footer-new__navlinks');
    await page.locator('.ma__footer-new__copyright');
    await page.locator('.ma__footer-new__logo');
    await page.locator('.ma__footer-new__container').hover();
    await page.locator('.ma__footer-new__copyright--bold').hover();
  }

  await page.waitForTimeout(3 * 1000);
}
