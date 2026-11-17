const readiness = require('./readiness');

module.exports = async (page, scenario, viewport, isReference, browserContext, config = {}) => {
  const capture = readiness.monitor(page, scenario, viewport, isReference, config);
  await page.addInitScript(require('./tableau-readiness'));
  const banned = [
    'www.googletagmanager.com',
    'script.crazyegg.com',
    'www.google-analytics.com',
    'js-agent.newrelic.com',
    'translate.google.com',
    'foresee.com',
    'bam.nr-data.net',
    'maps.googleapis.com',
    '9p83os0fkf.execute-api.us-east-1.amazonaws.com/v1/waittime',
    'https://massgov.github.io/FWE/PondMaps/dfw-pond-maps-table.html',
  ];
  await page.route('**/*', (route) => {
    const url = route.request().url();
    const matches = banned.filter(ban => url.includes(ban));
    if (matches.length) {
      capture.blocked.add(url);
      return route.abort();
    }
    return route.fallback();
  });

  if (process.env.MASS_BYPASS_RATE_LIMIT) {
    await browserContext.setExtraHTTPHeaders({ 'mass-bypass-rate-limit': process.env.MASS_BYPASS_RATE_LIMIT });
  }

  const url = new URL(isReference && scenario.referenceUrl || scenario.url);
  await browserContext.addCookies([
    {
      name: 'im-bypass',
      value: 'true',
      url: url.origin,
    },
  ]);

  try {
    await readiness.warmup(browserContext, url.href, scenario.expectedStatus ?? 200);
  } catch (error) {
    readiness.save(page, { outcome: 'readiness-failure', stage: 'warmup', error: error.message });
    throw error;
  }
};
