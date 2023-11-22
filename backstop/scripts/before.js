module.exports = async (page, scenario, viewport, isReference, browserContext) => {
  const banned = [
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
  ];
  await page.route('**/*', (route) => {
    const url = route.request().url();
    const matches = banned.filter(ban => url.includes(ban));
    return matches.length ? route.abort() : route.continue();
  });

  await browserContext.setExtraHTTPHeaders(
    {'mass-bypass-rate-limit': process.env.MASS_BYPASS_RATE_LIMIT}
  );

  let cookies = [
    {
      "expirationDate": 1798790400,
      "path": "/",
      "name": "im-bypass",
      "value": "true",
      "hostOnly": false,
      "httpOnly": false,
      "secure": false,
      "session": false,
      "sameSite": "Lax"
    }
  ];
  // Override the domain based on what we are testing.
  const url = new URL(scenario.url);
  cookies = cookies.map(cookie => {
    if (url.host === 'mass-web') {
      cookie.domain = "mass-web";
    }
    else {
      cookie.domain = "." + url.host;
    }
    return cookie;
  });

  await browserContext.addCookies(cookies);

  const ignoredMessages = [
    'New Relic',
    'BackstopTools have been installed'
  ];
  console.log = (message) => {
    if (typeof message === 'string' || message instanceof String) {
      ignoredMessages.some(ignore => message.includes(ignore)) ? undefined : process.stdout.write(`${message}\n`);
    }
    else {
      process.stdout.write(`${message}\n`);
    }
  };
}
