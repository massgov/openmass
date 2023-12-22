const { chromium } = require('playwright');
const percySnapshot = require('@percy/playwright');

const target = opts.length ? opts[0].replace('--target=', '') : 'prod';

(async () => {
  const pages = require('./all');;

  const scenarios = pages.map(function(page) {
    let base = process.env.BASE_URL;
    let auth = false;
    let removeSelectors = [];

    switch (target) {
      case 'prod':
        base = 'https://www.mass.gov';
        break;
      case 'local':
        base = 'http://mass-web';
        break;
      case 'test':
        auth = getAuth();
        base = `https://${auth.username}:${auth.password}@stage.mass.gov`;
        break;
      case 'tugboat':
        const opts = process.argv.filter(arg => arg.match(/^--tugboat=/))
        if (opts.length < 1) {
          throw '--tugboat must be specified with a preview URL if --target=tugboat is set.'
        }
        base = opts[0].replace('--tugboat=', '');
        break;
      default:
        auth = getAuth();
        base = `https://${auth.username}:${auth.password}@${target}.edit.mass.gov`;
    }
    const url = new URL(`${base}${page.url}`);
    let separator = "?";
    if (url.search !== "") {
      separator = "&";
    }
    if (!page.showHeaderAlerts) {
      removeSelectors.push('.pre-content .mass-alerts-block');
    }
    if (!page.showGlobalAlerts) {
      removeSelectors.push('.mass-alerts-block[data-alerts-path="/alerts/sitewide"]');
    }

    const browser = await chromium.launch();
    const page = await browser.newPage();
    await page.goto(url, { waitUntil: 'networkidle' });
    await percySnapshot(page, scenario.label);
    await browser.close();
  });

})();
