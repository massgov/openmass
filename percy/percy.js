const { chromium } = require('playwright');
const percySnapshot = require('@percy/playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('http://example.com/', { waitUntil: 'networkidle' });
  await percySnapshot(page, 'Example Site');
  await browser.close();
})();
