
module.exports = async (page, scenario, vp) => {
    // Load cookies from scripts/cookies.js by default.
    await require('./loadCookies')(page, scenario);

    if (scenario.auth) {
      await page.authenticate(scenario.auth)
    }
    await page.setRequestInterception(true);
    page.on('request', createRequestInterceptor(scenario));
}

function escapeRegexp(string) {
  return string.replace(/[.*+\-?^${}()|[\]\\]/g, '\\$&');
}

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
// Block scripts that do analytics tracking, have side effects based on
// domain, or cause timeouts on page load because they load in lots of extra
// stuff.
const bannedRE = new RegExp(banned.map(escapeRegexp).join('|'))

function createRequestInterceptor(scenario) {

  return function intercept(request) {
    let urlMatch;
    const url = request.url();

    // Replace static maps with a placeholder image of the same size.
    if(urlMatch = url.match(/maps\.googleapis\.com\/maps\/api\/staticmap.*size=(\d+x\d+)/)) {
      return request.respond({
        status: 301,
        headers: {"Location": `https://via.placeholder.com/${urlMatch[1]}/B2DEA2.png?text=Static%20Map`}
      });
    }
    // Replace hero images with placeholder images. Hero images can be randomized, so we just
    // replace them with their placeholder equivalents.
    if(urlMatch = url.match(/\/files\/styles\/hero(\d+x\d+)/)) {
      return request.respond({
        status: 301,
        headers: {Location: `https://via.placeholder.com/${urlMatch[1]}.png?text=Hero%20Image`}
      });
    }

    if(!scenario.showAlerts && url.match(/\/jsonapi\/node\/alert/)) {
      return request.respond({
        status: 200,
        headers: {"Content-Type": 'application/json'},
        body: JSON.stringify({
          jsonapi: {},
          data: [],
          included: [],
          links: {}
        })
      });
    }

    if(bannedRE.test(url)) {
      return request.abort()
    } else {
      return request.continue();
    }
  }
}
