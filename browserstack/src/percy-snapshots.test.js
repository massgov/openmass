const { Builder, By, Key, until, Capabilities } = require("selenium-webdriver");
const { percy } = require('browserstack-node-sdk');

describe("massgov-screenshots", () => {
  let base;
  let driver;
  let pages;
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

  beforeAll(() => {
    driver = new Builder()
      .usingServer(`http://localhost:4444/wd/hub`)
      .withCapabilities(Capabilities.chrome())
      .build();
  });

  afterAll(async () => {
    await driver.quit();
  })

  pages.forEach(async (page) => {
      test(page.label + ' test', async () => {
        await driver.get(base + page.url);
        let options = {
          fullPage: true,
          ignore_region_selectors: [
            'div.ma__banner-credit',
          ]
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
