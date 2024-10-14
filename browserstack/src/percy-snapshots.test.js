const { Builder, By, Key, until, Capabilities } = require("selenium-webdriver");
const chrome = require('selenium-webdriver/chrome');
const { percyScreenshot } = require('@percy/selenium-webdriver');

describe("massgov-screenshots", () => {
  let base;
  let driver;
  let pages;
  let capabilities;
  const auth = getAuth();
  const list = process.env.PERCY_LIST;
  const target = process.env.PERCY_TARGET;
  const tugboat = process.env.PERCY_TUGBOAT;

  switch (list) {
    case 'all':
      pages = require('../all');
      break;
    case 'post-release':
      pages = require('../post-release');
      break;
    default:
      throw new Error('Error occurred with ' + list + '.');
  }

  switch (target) {
    case 'prod':
      base = 'https://www.mass.gov';
      break;
    case 'test':
      base = `https://stage.mass.gov`;
      break;
    case 'tugboat':
      base = tugboat;
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
    driver = await new Builder()
      .forBrowser('chrome')
      .setChromeOptions(new chrome.Options())
      .build();

    await driver.sendDevToolsCommand('Network.setExtraHTTPHeaders', {
      headers: {
        "mass-bypass-rate-limit": "${process.env.MASS_BYPASS_RATE_LIMIT}"
      }
    });
    await driver.sendDevToolsCommand('Network.setUserAgentOverride', {
      userAgent: 'massgov-percy'
    });
    await driver.sendDevToolsCommand('Network.setBlockedURLs', {
      urls: [
        'www.googletagmanager.com',
        'script.crazyegg.com',
        'www.google-analytics.com',
        'js-agent.newrelic.com',
        'translate.google.com',
        'foresee.com',
        'www.youtube.com',
        'bam.nr-data.net',
        'maps.googleapis.com',
        '9p83os0fkf.execute-api.us-east-1.amazonaws.com/v1/waittime',
        'player.vimeo.com',
        'https://massgov.github.io/FWE/PondMaps/dfw-pond-maps-table.html',
      ]
    });
  });

  afterAll(async () => {
    await driver.quit();
  });

  pages.forEach((page) => {
    test(page.label + ' test', async () => {
      await driver.get(base + page.url);

      let options = {
        fullPage: true,
        widths: [320, 1024, 1920],
        ignore_region_selectors: [],
        requestHeaders: {
          'mass-bypass-rate-limit': process.env.MASS_BYPASS_RATE_LIMIT.replace(/(^["']|["']$)/g, ''),
        }
      };
      await percyScreenshot(driver, page.label, options);
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
