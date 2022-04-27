// Determine the list of urls to use with backstop
const opt = process.argv.filter(arg=>arg.match(/^--list=/))
const file = opt.length ? opt[0].replace('--list=', '') : 'page';

// When running many browsers at once, individual browser instances may become
// blocked for a very long time. The typical navigation timeout is 30 seconds.
// By extending it to 5 minutes, we allow tests to pass even if some of them
// are very slow. This allows us to crank up the concurrency to a high number,
// as we don't care about performance or 95th percentiles in screenshot tests.
// Note it's still possible we have an actual performance or locking issue in
// the app code. However, given we haven't had production outages or reports of
// "random" slowness after deployments it seems unlikely. Unfortunately, the
// waitTimeout setting isn't currently documented upstream, but we can see it
// in use:
// https://github.com/garris/BackstopJS/blob/c2de5b3a29d9485054461563c6992602569e357c/core/util/runPuppet.js#L83
const waitTimeout = 5 * 60 * 1000

let pages;

switch (file) {
  case 'page':
    pages = require('./pages');
    break;
  case 'all':
    pages = require('./all');
    break;
  case 'post-release':
    pages = require('./post-release');
    break;
  default:
    throw new Error('has occurred with' + file + '.');
}

// Determine the environment we're targeting.
const opts = process.argv.filter(arg => arg.match(/^--target=/))
const viewportConfig = process.argv.filter(arg => arg.match(/^--viewport=/))
const viewportArg = viewportConfig.length ? viewportConfig[0].replace('--viewport=', '') : 'all';

const target = opts.length ? opts[0].replace('--target=', '') : 'prod';

const scenarios = pages.map(function(page) {
  let base = process.env.BASE_URL;
  let auth = false;

  switch (target) {
    case 'prod':
      base = 'https://www.mass.gov';
      break;
    case 'local':
      base = 'http://mass-web';
      break;
    case 'test':
      base = 'https://massgovstg.prod.acquia-sites.com';
      auth = getAuth();
      break;
    case 'tugboat':
      const opts = process.argv.filter(arg => arg.match(/^--tugboat=/))
      base = opts[0].replace('--tugboat=', '');
      break;
    default:
      base = `https://massgov${target}.prod.acquia-sites.com`;
      auth = getAuth();
  }
  const url = new URL(`${base}${page.url}`);
  let separator = "?";
  if (url.search !== "") {
    separator = "&";
  }
  if (page.screens !== undefined) {
    if (page.screens.length > 0 && page.viewports === undefined) {
      page.viewports = page.screens.map(function (screen) {
        let viewport;
        switch (screen) {
          case "desktop":
            viewport = {
              "label": "desktop",
              "width": 1920,
              "height": 1080
            };
            break;
          case "tablet":
            viewport = {
              "label": "tablet",
              "width": 1024,
              "height": 768
            };
            break;
          case "mobile":
            viewport = {
              "label": "phone",
              "width": 320,
              "height": 480
            };
            break;
        }
        return viewport;
      });
    }
  }
  return {
    ...page,
    url: `${base}${page.url}${separator}cachebuster=${Math.random().toString(36).substring(7)}`,
    misMatchThreshold: 0.05,
    auth,
  }
});

function getAuth() {
  // Trim leading and trailing quotes off of the auth variables.
  // This works around docker-compose's handling of environmnent
  // variables with quotes.
  return {
    username: process.env.LOWER_ENVIR_AUTH_USER.replace(/(^["']|["']$)/g, ''),
    password: process.env.LOWER_ENVIR_AUTH_PASS.replace(/(^["']|["']$)/g, '')
  }
}

const viewports = [
  {
    "label": "desktop",
    "width": 1920,
    "height": 1080
  }
];

if (viewportArg !== 'desktop') {
  viewports.push(
    {
      "label": "phone",
      "width": 320,
      "height": 480
    },
    {
      "label": "tablet",
      "width": 1024,
      "height": 768
    },
  );
}

module.exports = {
    id: 'regression',
    viewports,
    "scenarios": scenarios,
    "paths": {
        "bitmaps_reference": `${__dirname}/reference`,
        "bitmaps_test": `${__dirname}/runs`,
        "engine_scripts": `${__dirname}/scripts`,
        "html_report": `${__dirname}/report`,
        "ci_report": `${__dirname}/report`,
    },
    "onBeforeScript": "before.js",
    "onReadyScript": "ready.js",
    "report": ["browser", "CI"],
    "engine": "puppeteer",
    "engineFlags": [],
    "engineOptions": {
        "ignoreHTTPSErrors": true,
        "waitTimeout": waitTimeout,
        "args": [
            "--no-sandbox",
            "--disable-setuid-sandbox",
            "--enable-features=NetworkService",
            "--ignore-certificate-errors"
        ]
    },
    "asyncCaptureLimit": 16,
    "asyncCompareLimit": 8,
    "debug": false,
    "debugWindow": false
}
