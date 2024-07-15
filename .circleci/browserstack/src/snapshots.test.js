const { Builder, By, Key, until, Capabilities } = require("selenium-webdriver");
const { percy } = require('browserstack-node-sdk');

describe("BStack demo test", () => {
  let driver;
  const paths = require('../post-release');

  beforeAll(() => {
    driver = new Builder()
      .usingServer(`http://localhost:4444/wd/hub`)
      .withCapabilities(Capabilities.chrome())
      .build();
  });

  afterAll(async () => {
    await driver.quit();
  })

  paths.forEach(async (path) => {
      test(path.label + ' test', async () => {
        await driver.get('https://stage.mass.gov' + path.url);
        let options = {
          fullPage: true,
          ignore_region_selectors: [
            'div.ma__banner-credit',
          ]
        }
        await percy.screenshot(driver, path.label, options);
      });
  });

});
