module.exports = async (page, scenario, viewport) => {
  console.log(`SCENARIO > ${scenario.label}: ${viewport.label}`);

  async function waitForFlexImageLayout(page, containerSelector, imgSelector = 'img') {
    console.log(`⏳ [waitForFlexImageLayout] Starting for: ${containerSelector}`);

    // 1. Wait for all images to load or fail
    await page.evaluate(async ({ containerSelector, imgSelector }) => {
      const containerEls = Array.from(document.querySelectorAll(containerSelector));
      const images = containerEls.flatMap(el => Array.from(el.querySelectorAll(imgSelector)));

      console.log(`[waitForFlexImageLayout] Found ${images.length} images to check.`);

      await Promise.all(
        images.map(img =>
          img.complete
            ? Promise.resolve()
            : new Promise(resolve => {
              let resolved = false;

              const done = () => {
                if (!resolved) {
                  resolved = true;
                  resolve();
                }
              };

              img.onload = done;
              img.onerror = done;

              // Failsafe after 10s
              setTimeout(() => {
                console.warn('[waitForFlexImageLayout] Image load timeout hit.');
                done();
              }, 10000);
            })
        )
      );
    }, { containerSelector, imgSelector });

    console.log(`✅ [waitForFlexImageLayout] All images in ${containerSelector} finished loading.`);

    // 2. Wait for display:flex to apply to containers
    await page.evaluate(async ({ containerSelector }) => {
      const containers = Array.from(document.querySelectorAll(containerSelector));
      console.log(`[waitForFlexImageLayout] Checking ${containers.length} containers for flex display...`);

      await Promise.all(
        containers.map(el =>
          new Promise(resolve => {
            const start = Date.now();

            const checkFlex = () => {
              const display = getComputedStyle(el).display;
              if (display === 'flex') {
                resolve();
              } else if (Date.now() - start > 10000) {
                console.warn('[waitForFlexImageLayout] Flex check timed out.');
                resolve(); // continue anyway
              } else {
                setTimeout(checkFlex, 1000);
              }
            };
            checkFlex();
          })
        )
      );
    }, { containerSelector });

    console.log(`✅ [waitForFlexImageLayout] display:flex confirmed/timeout passed.`);

    // 3. Wait for scrollHeight to stop changing (layout settle)
    await page.evaluate(async () => {
      return new Promise(resolve => {
        let lastHeight = document.body.scrollHeight;
        let stableChecks = 0;
        let tries = 0;

        const check = () => {
          const newHeight = document.body.scrollHeight;
          if (newHeight === lastHeight) {
            stableChecks++;
            if (stableChecks >= 3) {
              return resolve();
            }
          } else {
            stableChecks = 0;
            lastHeight = newHeight;
          }

          tries++;
          if (tries >= 50) {
            console.warn('[waitForFlexImageLayout] Layout check timed out.');
            return resolve();
          }

          setTimeout(check, 150);
        };

        check();
      });
    });

    console.log(`✅ [waitForFlexImageLayout] Layout is stable.`);
  }

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

      /* Make sure sticky nav stays at the top */
      /* @todo it'd be better to add a class to this which disables to functionality */
      .ma__sticky-nav {
        top: auto !important;
        bottom: -15px !important;
        position: absolute !important;
        z-index: 80;
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

  // Wait for Tableaus to load.
  // The screenshot won't show all the tableaus https://github.com/microsoft/playwright/issues/17904
  let tableaus = await page.locator('.ma_tableau_container');
  let tableausCount = await tableaus.count();
  for (let i = 0; i < tableausCount; i++) {
    await page.frameLocator('.ma_tableau_container iframe').nth(i).locator('#initializing_thin_client').waitFor({ 'state': 'hidden' });
  }

  // Wait for iFrame resizer.
  await page.waitForFunction(() => document.querySelectorAll('.js-ma-responsive-iframe iframe[height=auto]').length === 0);

  // Wait for sticky nav to shift.
  const stickyNavs = await page.locator('.ma__sticky-nav').count();
  if (stickyNavs > 0) {
    // Remove active class.
    await page.evaluate(() => { for (link of document.querySelectorAll('.ma__sticky-nav__link')) { link.classList.remove('is-active'); } });
    await page.waitForSelector('.ma__sticky-nav');
  }

  switch (scenario.label) {
    case 'InfoDetails1':
    case 'InfoDetailsImageWrapLeft':
    case 'InfoDetailsImageWrapRight':
    case 'InfoDetailsImageNoWrapLeft':
    case 'InfoDetailsImageNoWrapRight':
    case 'InfoDetailsImageLeftAlign':
    case 'InfoDetailsImageRightAlign':
      await page.waitForSelector('.ma__fixed-feedback-button');
      break;
    case 'OrgElectedOfficial':
    case 'ExpansionOfAccordions1':
      await page.evaluate(() => document.querySelector('.ma__sticky-nav').setAttribute('data-sticky', 'bottom'));
      await page.waitForSelector('.ma__sticky-nav');
      break;
    case 'ServiceDetails':
      await page.frameLocator('.ma__iframe__container.js-ma-responsive-iframe iframe').first().locator('button').waitFor();
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
      await waitForFlexImageLayout(page, '.ma__card__wrapper');
      await waitForFlexImageLayout(page, '.ma__campaign-feature-2up__wrapper');
      await waitForFlexImageLayout(page, '.ma__card__details ma__card__details--secondary');

      await page.waitForTimeout(6 * 1000);

      break;
  }

  // Wait for any layout shift that nudges the footer.
  if (scenario.label !== '404') {
    await page.waitForSelector('.ma__footer-new');
    await page.locator('.ma__footer-new__navlinks');
    await page.locator('.ma__footer-new__copyright');
    await page.locator('.ma__footer-new__logo');
    await page.locator('.ma__footer-new__container').waitFor();
    await page.locator('.ma__footer-new__copyright--bold').hover();
  }

  await page.waitForTimeout(8 * 1000);
}
