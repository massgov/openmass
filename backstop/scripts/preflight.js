const { chromium } = require('playwright');
const config = require('../backstop');
const before = require('./before');
const readiness = require('./readiness');

async function preflight() {
  const representative = config.scenarios.find(scenario => scenario.label === 'Homepage') ||
    config.scenarios.find(scenario => (scenario.expectedStatus ?? 200) === 200);
  if (!representative) throw new Error('No representative page configured for readiness checks');
  const scenario = { ...representative, label: `preflight-${representative.label}`, captureTimeout: 120000 };
  const browser = await chromium.launch({ headless: true, args: config.engineOptions.args });
  try {
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();
    await before(page, scenario, config.viewports[0], false, context);
    await readiness.guard(page, scenario, async step => {
      page.setDefaultTimeout(10000);
      page.setDefaultNavigationTimeout(15000);
      let consecutive = 0;
      for (let attempt = 1; attempt <= 6; attempt++) {
        try {
          await step('preflight document', async () => {
            const response = await page.goto(scenario.url, { waitUntil: 'domcontentloaded' });
            if (response.status() !== 200) throw new Error(`Representative page returned HTTP ${response.status()}`);
          });
          await step('preflight stylesheets', () => readiness.styles(page, scenario));
          consecutive++;
          if (consecutive === 2) return { consecutiveSuccessfulChecks: consecutive };
        } catch (error) {
          consecutive = 0;
          console.warn(`[Readiness] Preflight attempt ${attempt} failed (${error.name})`);
        }
        await page.waitForTimeout(1000);
      }
      throw new Error('Site failed to pass two consecutive document and stylesheet checks');
    });
    console.log('[Readiness] Site passed two consecutive document and stylesheet checks');
  } finally {
    await browser.close();
  }
}

if (require.main === module) preflight().catch(error => { console.error(error.message); process.exitCode = 1; });
module.exports = preflight;
