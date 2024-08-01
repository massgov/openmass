const { Builder, By, Key, until, Capabilities } = require("selenium-webdriver");
const { percy } = require('browserstack-node-sdk');

describe("massgov-screenshots", () => {
  let base;
  let driver;
  let pages;
  let capabilities;
  const auth = getAuth();
  const file = process.env.PERCY_FILE;
  const target = process.env.PERCY_TARGET;

  switch (file) {
    case 'all':
      pages = require('../all');
      break;
    case 'post-release':
      pages = require('../post-release');
      break;
    default:
      throw new Error('Error occurred with ' + file + '.');
  }

  switch (target) {
    case 'prod':
      base = 'https://www.mass.gov';
      break;
    case 'test':
      base = `https://stage.mass.gov`;
      break;
    default:
      base = `https://${auth.username}:${auth.password}@${target}.edit.mass.gov`;
  }

  beforeAll(async () => {
    // Functionality currently unavailable, but is in beta: https://www.browserstack.com/docs/automate/selenium/custom-header
    // capabilities = {
    //   'bstack:options': {
    //     "headerParams": `{"mass-bypass-rate-limit":"${process.env.MASS_BYPASS_RATE_LIMIT}"}`
    //   }
    // }
    capabilities = {};
    driver = await new Builder()
      .withCapabilities(capabilities)
      .build();
  });

  afterAll(async () => {
    await driver.quit();
  });

  pages.forEach((page) => {
    test(page.label + ' test', async () => {
      await driver.get(base + page.url);

      // Inject JavaScript to set custom headers
      await driver.executeScript(`
        (function() {
          function interceptFetch() {
            const originalFetch = window.fetch;
            window.fetch = function(url, options = {}) {
              options.headers = options.headers || {};
              options.headers['mass-bypass-rate-limit'] = '${process.env.MASS_BYPASS_RATE_LIMIT}';
              return originalFetch(url, options);
            };
          }

          function interceptXHR() {
            const originalOpen = XMLHttpRequest.prototype.open;
            XMLHttpRequest.prototype.open = function(method, url, async, user, pass) {
              this.setRequestHeader('mass-bypass-rate-limit', '${process.env.MASS_BYPASS_RATE_LIMIT}');
              originalOpen.call(this, method, url, async, user, pass);
            };
          }

          interceptFetch();
          interceptXHR();
        })();
      `);

      // Inject styles and handle dynamic content
      // await driver.executeScript(`
      //   // Add custom styles
      //   const style = document.createElement('style');
      //   style.textContent = \`
      //     *, *::before, *::after {
      //       animation-duration: 0s !important;
      //       transition-duration: 0s !important;
      //     }
      //     body.is-front .ma__search-banner {
      //       background: none !important;
      //     }
      //     .ma__header__hamburger__menu-button {
      //       outline: none !important;
      //     }
      //     #sticky-toc {
      //       display: none !important;
      //     }
      //     .ma__sticky-nav {
      //       top: auto !important;
      //       bottom: -15px !important;
      //       position: absolute !important;
      //       z-index: 80;
      //     }
      //     .ma__organization-navigation.stuck {
      //       position: static !important;
      //       top: auto !important;
      //       left: auto !important;
      //       width: auto !important;
      //       margin-top: -20px !important;
      //       z-index: auto !important;
      //     }
      //     .ma__org-page .pre-content {
      //       padding-top: 0 !important;
      //     }
      //   \`;
      //   document.head.append(style);
      //
      //   // Handle frequently changing content
      //   document.querySelectorAll('.ma__search-banner__image-name').forEach(e => e.innerText = 'Good Picture');
      //   document.querySelectorAll('.ma__search-banner__image-author').forEach(e => e.innerText = 'John Smith');
      //   document.querySelectorAll('.ma__search-banner__links .ma__link-list__item a').forEach(e => e.innerText = 'Popular search query');
      //   document.querySelectorAll('.ma__stacked-row__section .ma__key-actions .ma__callout-link .ma__callout-link__container .ma__callout-link__text').forEach(e => e.innerText = 'Featured service link text');
      //   document.querySelectorAll('.ma__split-columns__column > .ma__rich-text').forEach(e => e.innerHTML = '<article style="background-color: #888;"><span style="display: block; width: 100%; max-width: 100%; height: auto;">&nbsp;</span></article><h5>News title</h5>Teaser text');
      //   document.querySelectorAll('.ma__featured-item__title-container .ma__featured-item__title span').forEach(e => e.innerText = 'Featured item title');
      //   document.querySelectorAll('.ma__press-listing__secondary-item .ma__press-teaser__details').forEach(e => e.innerText = 'Press teaser details');
      //
      //   // Wait for necessary elements
      //   return new Promise((resolve) => {
      //     setTimeout(resolve, 2000);
      //   });
      // `);

      // {"label": "Document", "url": "/media/1268726"},
      // {"label": "BinderAudit", "url": "/audit/qag-binderaudit"},
      // {"label": "CampaginLandingHeaderBg", "url": "/qagcampaign-landing-with-image-key-message-header"},
      // {"label": "CuratedListLinksDocs", "url": "/lists/qag-curatedlist"},
      // {"label": "DecisionOrder", "url": "/order/qag-decisionorder"},
      // {"label": "EventGeneralFuture", "url": "/event/qag-eventgeneralfuture-2018-07-25t122000-0400-2050-07-25t122000-0500"},
      // {"label": "FormWithFileUpload", "url": "/forms/qag-formwithfileuploads"},
      // {"label": "Guide", "url": "/guides/qag-guide"},
      // {"label": "HowToRequest", "url": "/how-to/qag-request-help-with-a-computer-problem"},
      // {"label": "InfoDetails1", "url": "/info-details/qag-information-details1"},
      // {"label": "InfoDetailsLanding", "url": "/info-details/qag-info-detail-with-landing-page-features"},
      // {"label": "NewsPressRelease", "url": "/news/qag-newspressrelease"},
      // {"label": "OrgQAGEOTSSGeneralOrg", "url": "/orgs/qag-executive-office-of-technology-services-and-security"},
      // {"label": "OrgElectedOfficial", "url": "/orgs/qag-test-elected-org-page"},
      // {"label": "RegulationEffectiveDate", "url": "/regulations/900-CMR-2-qag-regulation-title"},
      // {"label": "Service1", "url": "/qag-service1"},
      // {"label": "TopicPage1", "url": "/topics/qag-topicpage1"},

      // Scenario-specific actions
      switch (page.label) {
        case 'ExpansionOfAccordions1':
          await driver.executeScript(() => {
            document.querySelector('.ma__sticky-nav').setAttribute('data-sticky', 'bottom');
          });
          await driver.wait(until.elementLocated(By.css('.ma__sticky-nav')));
          break;
      }

      let options = {
        fullPage: true,
        ignore_region_selectors: []
      };
      await percy.screenshot(driver, page.label, options);
    });
  });
});

function getAuth() {
  // Trim leading and trailing quotes off of the auth variables.
  // This works around docker-compose's handling of environment
  // variables with quotes.
  return {
    username: process.env.LOWER_ENVIR_AUTH_USER.replace(/(^["']|["']$)/g, ''),
    password: process.env.LOWER_ENVIR_AUTH_PASS.replace(/(^["']|["']$)/g, '')
  };
}
