const { Builder, By, Key, until, Capabilities } = require("selenium-webdriver");
const { percy } = require('browserstack-node-sdk');

describe("massgov-screenshots", () => {
  let base;
  let driver;
  let pages;
  let capabilties;
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
      throw new Error('has occurred with' + file + '.');
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
    // capabilties = {
    //   'bstack:options': {
    //     "headerParams": `{"mass-bypass-rate-limit":"${process.env.MASS_BYPASS_RATE_LIMIT}"}`
    //   }
    // }
    capabilties = {};
    driver = new Builder()
      .withCapabilities(capabilties)
      .build();

    // Inject JavaScript to modify request headers
    await driver.executeScript(`
      (function(open) {
        XMLHttpRequest.prototype.open = function(method, url, async, user, pass) {
          this.addEventListener('readystatechange', function() {
            if (this.readyState === 1) {
              this.setRequestHeader('mass-bypass-rate-limit', '${process.env.MASS_BYPASS_RATE_LIMIT}');
            }
          }, false);
          open.call(this, method, url, async, user, pass);
        };
      })(XMLHttpRequest.prototype.open);
    `);
  });

  afterAll(async () => {
    await driver.quit();
  })

  pages.forEach(async (page) => {
      test(page.label + ' test', async () => {
        await driver.get(base + page.url);
        let options = {
          fullPage: true,
          ignore_region_selectors: []
        }
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
  }
}
