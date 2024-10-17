// Determine the list of urls to use with backstop
const opt = process.argv.filter(arg=>arg.match(/^--list=/));
const file = opt.length ? opt[0].replace('--list=', '') : 'all';
const withCache = process.argv.filter(arg=>arg.match(/^--cachebuster/)).length > 0;

let pages;

switch (file) {
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
              "width": 360,
              "height": 480
            };
            break;
        }
        return viewport;
      });
    }
  }
  if (!page.showHeaderAlerts) {
    removeSelectors.push('.pre-content .mass-alerts-block');
  }
  if (!page.showGlobalAlerts) {
    removeSelectors.push('.mass-alerts-block[data-alerts-path="/alerts/sitewide"]');
  }
  return {
    ...page,
    url: withCache ? `${base}${page.url}${separator}cachebuster=${Math.random().toString(36).substring(7)}` : `${base}${page.url}`,
    misMatchThreshold: 0.1,
    removeSelectors,
  }
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
      "width": 360,
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
    "readyTimeout": "90000",
    "asyncCaptureLimit": 3,
    "report": ["browser", "CI"],
    "engine": "playwright",
    "engineFlags": [],
    "engineOptions": {
        "browser": "chromium",
        "gotoParameters": {
          "waitUntil": "domcontentloaded",
        },
        "ignoreHTTPSErrors": true,
        "args": [
          "--no-sandbox",
          "--disable-setuid-sandbox",
          "--disable-gpu",
          "--force-device-scale-factor=1",
          "--disable-infobars=true",
          "--hide-scrollbars"
        ]
    },
    "debug": false,
    "debugWindow": false
}
