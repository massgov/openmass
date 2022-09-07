const os = require("os");

/**
 * Ready script, fires after pages have loaded, but before screenshots are
 * captured.
 *
 * This script is used to hide or modify highly dynamic elements that may cause
 * trouble during visual regression testing.  If you are constantly seeing
 * trivial failures for an element, you can probably deal with it here.
 */
module.exports = async function (page, scenario, vp) {
  console.log(`Preparing ${page.url()}`);
  const oneMinute = os.loadavg()[0];
  if (oneMinute > os.cpus().length) {
    console.log(`One minute load average is ${oneMinute}. Consider reducing the number of capture processes.`)
  }

  // DO NOT put anything that modifies a mass.gov page before this point.
  // Otherwise, if a Tugboat preview is suspended and needs to resume, we may
  // alter the state of the Tugboat "Preview is resuming" page and not the
  // mass.gov page.

  // The Tugboat preview is suspended. While Tugboat returns HTTP 418 in this
  // state, the page goes through several steps before finishing. Notably, the
  // title tag will contain something like "Tugboat - Preview is...", which
  // we can wait for. If the server doesn't respond with a mass.gov page in
  // 60 seconds, this will time out. To manually suspend a preview for testing,
  // use `tugboat suspend <id>` at the command line.
  if (new RegExp('.*tugboat.qa.*').test(page.url())) {
    try {
      await page.waitForFunction("new RegExp('.*Tugboat.*').test(document.title) !== true", {timeout: 60 * 1000})
    }
    catch (e) {
      throw new Error(`${e.constructor.name}: Tugboat did not load the preview. Please check ${page.url()} in your browser to see if the preview is marked as failed.`)
    }
  }

  // Since we're waiting on the page, the above can pass early, before jQuery
  // and other JS has had a chance to initialize.
  try {
    await page.waitForFunction("typeof window.jQuery == 'function'")
  }
  catch (e) {
    throw new Error(`${e.constructor.name}: jQuery was not found. This is usually caused by the server returning a 500 response. Please check ${page.url()} in your browser.`)
  }

  await require('./clickAndHoverHelper')(page, scenario);

  await page.evaluate(function (url) {
    // Disable jQuery animation for any future calls.
    jQuery.fx.off = true;

    // Zero delay on CSS transitions.
    jQuery("head").append('<style type="text/css"> *, *:before, *:after { transition-duration: 0s !important } </style>');

    // Immediately complete any in-progress animations.
    jQuery(':animated').finish();

    // Undo the Google Optimize page-hiding snippet so we can access the page
    // before the 2s timeout. See https://developers.google.com/optimize.
    if (window.dataLayer && window.dataLayer.hide && window.dataLayer.hide.end) {
      window.dataLayer.hide.end();
    }

    // Replace random image credit on homepage.
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
  }, scenario.url);

  // Finally, wait for ajax to complete - this is to give alerts
  // time to finish rendering. This can take a while, especially
  // in local environments.
  await page.waitForFunction('jQuery.active == 0');

  // All the alerts on the page must be processed.
  await page.waitForFunction("jQuery('.mass-alerts-block:not([data-alert-processed])').length === 0");

  let leafletMapInitialized = await page.evaluate(async function () {
    let initialized = undefined;
    const containers = document.querySelectorAll(".js-leaflet-map");
    if (containers.length) {
      containers.forEach(function (e) {
        const container = L.DomUtil.get(e);
        if (container != null) {
          if (container._leaflet_id == null) {
            initialized = false;
          }
          else {
            initialized = true;
          }
        }
      });
    }
  })

  if (leafletMapInitialized == false) {
    await page.waitForSelector('.js-leaflet-map .leaflet-tile-container', {
      visible: true,
      timeout: 10000,
    })
    await page.waitForTimeout(3000);
  }
  else if (leafletMapInitialized == true) {
    await page.waitForTimeout(5000);
  }

  if (!scenario.hideAlerts || scenario.hideAlerts === undefined) {
    // Wait for a selector to become visible.
    let expanded = await page.evaluate(async function () {
      let result = undefined;
      var el = document.querySelector( '.ma__emergency-header__toggle');
      if (el !== null) {
        if (el.getAttribute('aria-expanded') == true) {
          result = true;
        }
        else {
          result = false;
        }
      }
      return result;
    });
    if (expanded == true) {
      await page.waitForSelector('span.ma__emergency-alert__time-stamp', {
        visible: true,
        timeout: 10000,
      })
      await page.evaluate(async function () {
        document.querySelector('.ma__emergency-alert__time-stamp').innerText = 'May. 24th, 2021, 5:00 pm';
        document.querySelector('.ma__emergency-alert__link a.ma__content-link span:first-child').innerText = 'Everyone age 5+ should get a COVID-19 booster. Anyone age 50+ may get a second booster. See the latest updates as of ';
      });
    }
    else if (expanded == false) {
      await page.waitForSelector('.ma__emergency-header__toggle', {
        visible: true,
        timeout: 10000,
      })
    }

    await page.waitForTimeout(1000);
  }
  else {
    await page.evaluate(async function () {
      var el = document.querySelector('.mass-alerts-block');
      el.parentNode.removeChild(el);
    })
  }

  // Wait for iframes to be resized at least once.
  // Avoid iframes with fixed height.
  await page.waitForFunction("jQuery('.js-ma-responsive-iframe iframe[height=auto]').length === 0");
  await page.waitForFunction("document.readyState === 'complete'");
  switch (scenario.label) {
    case "InfoDetailsImageWrapLeft":
    case "InfoDetailsImageWrapRight":
    case "InfoDetailsImageNoWrapLeft":
    case "InfoDetailsImageNoWrapRight":
      await page.waitForSelector('form.ma__mass-feedback-form__form', {
        visible: true,
        timeout: 10000,
      });
      await page.waitForSelector(".ma__figure--x-large img", {
        visible: true,
        timeout: 10000,
      });
      await page.waitForTimeout(3000);
      break;
    case "Homepage Login link (Mobile)":
      await page.evaluate(async function () {
        document.querySelector("button.ma__header__hamburger__menu-button").click();
        jQuery(".ma__header__hamburger__utility-nav .ma__utility-nav__items li.ma__utility-nav__item:last-child button.ma__utility-nav__link").click();
        document.querySelector(".ma__header__hamburger__nav-container").scrollTo(0, 500);
      })
      break;
    case "InfoDetails1":
      await page.waitForSelector('.cbFormErrorMarker', {
        visible: true,
        timeout: 10000,
      })
      await page.evaluate(async function () {
        document.querySelector('.ma__sticky-toc__stuck').remove()
      })
      await page.waitForSelector('.ma__figure--large.ma__csvtable table#DataTables_Table_0', {
        visible: true,
        timeout: 10000,
      })
      await page.waitForSelector('.ma__figure--large.ma__csvtable div#DataTables_Table_0_info', {
        visible: true,
        timeout: 10000,
      })
      await page.waitForSelector('.ma__figure--large.ma__csvtable table#DataTables_Table_1', {
        visible: true,
        timeout: 10000,
      })
      await page.waitForSelector('.ma__figure--large.ma__csvtable div#DataTables_Table_1_info', {
        visible: true,
        timeout: 10000,
      })


      await page.waitForSelector('.ma__figure--x-large.ma__csvtable table#DataTables_Table_2', {
        visible: true,
        timeout: 10000,
      })

      await page.waitForSelector('.ma__figure--x-large.ma__csvtable div#DataTables_Table_2_info', {
        visible: true,
        timeout: 10000,
      })


      await page.waitForSelector('footer#footer .ma__footer-new__container', {
        visible: true,
        timeout: 10000,
      })

      await page.waitForSelector('.ma__fixed-feedback-button', {
        visible: true,
        timeout: 10000,
      })
      await page.waitForTimeout(3000);
      break;
    case "Service1":
    case "ExpansionOfAccordions1_toggle":
    case "ExpansionOfAccordions2_toggle":
      await page.evaluate(async function () {
        jQuery(".js-accordion-link").not('.ma__emergency-header__toggle').click();
      });
      await page.waitForTimeout(1000);
      break;
  }

  // Wait for all visible fonts to complete loading.
  await page.evaluate(async function () {
    await document.fonts.ready;
  })

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
      // Kill banner image on QAG campaign landing page (show a black box
      // instead)
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
      '}' +
      // [FREQUENTLY CHANGING CONTENT] Kill images in Updates From The
      // Baker-Polito Administration on Governor's page (show a gray box
      // instead)
      '.ma__featured-item {' +
      ' background-color: #888888;\n' +
      '}' +
      '.ma__featured-item__image, .ma__featured-item__image--large {' +
      '  position: relative;' +
      '}' +
      '.ma__featured-item__image:before, .ma__featured-item__image--large:before {' +
      '  background: #888888;\n' +
      '  content: \' \';\n' +
      '  display: block;\n' +
      '  position: absolute;\n' +
      '  top: 0;\n' +
      '  left: 0;\n' +
      '  right: 0;\n' +
      '  bottom: 0;\n' +
      '  z-index: 3;\n' +
      '}' +
      // [FREQUENTLY CHANGING CONTENT] Kill images and text in Recent news and
      // announcements on Governor's page (show a gray box instead)
      '.ma__press-listing__secondary-item .ma__press-teaser .ma__press-teaser__image {' +
      '  position: relative;' +
      '}' +
      '.ma__fixed-feedback-button {' +
      '  top: 28rem;' +
      '  bottom: unset;' +
      '}' +
      '.ma__fixed-feedback-button a {' +
      '  transition: unset;' +
      '}' +
      '.ma__figure--full {' +
      '  transition: unset;' +
      '}' +
      '.ma__press-listing__secondary-item .ma__press-teaser .ma__press-teaser__image:before {' +
      '  background: #888888;\n' +
      '  content: \' \';\n' +
      '  display: block;\n' +
      '  position: absolute;\n' +
      '  top: 0;\n' +
      '  left: 0;\n' +
      '  right: 0;\n' +
      '  bottom: 0;\n' +
      '  z-index: 3;\n' +
      '}' +
      // [FREQUENTLY CHANGING CONTENT] Kill banner background image on Home
      // page (show a gray box instead)
      '.ma__search-banner {' +
      '  position: relative;' +
      '}' +
      '.ma__search-banner:after {' +
      ' background: #888888;\n' +
      '  z-index: 1;\n' +
      '  content: \' \';\n' +
      '  display: block;\n' +
      '  position: absolute;\n' +
      '  top: 0;\n' +
      '  left: 0;\n' +
      '  right: 0;\n' +
      '  bottom: 0;\n' +
      '}' +
      'button:focus-visible, [type="button"]:focus-visible {' +
      'outline: none;\n' +
      '}'
  });

  // We can add a slight delay here. This can cover up jitter caused
  // by weird network conditions, slow JS, etc, but if we need an extra
  // delay after page load, it probably indicates there's a problem with
  // performance.
  // await page.waitForTimeout(2000);
}
