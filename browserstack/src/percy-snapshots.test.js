const { Builder, By, Key, until, Capabilities } = require("selenium-webdriver");
const { percy } = require('browserstack-node-sdk');

describe("massgov-screenshots", () => {
  let base;
  let driver;
  let pages;
  let capabilities;
  const auth = getAuth();
  const list = process.env.PERCY_LIST;
  const target = process.env.PERCY_TARGET;
  const tugboat = process.env.PERCY_TUGBOAT;

  console.log('the tugboat url is: ' + tugboat);

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

      let options = {
        fullPage: true,
        ignore_region_selectors: [],
        discovery: {
          requestHeaders: {
            'mass-bypass-rate-limit': process.env.MASS_BYPASS_RATE_LIMIT
          }
        }
      };
      console.log(process.env.MASS_BYPASS_RATE_LIMIT);
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
