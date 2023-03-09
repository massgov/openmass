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
      if (opts.length < 1) {
        throw '--tugboat must be specified with a preview URL if --target=tugboat is set.'
      }
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
    url: withCache ? `${base}${page.url}${separator}cachebuster=${Math.random().toString(36).substring(7)}` : `${base}${page.url}`,
    misMatchThreshold: 0.1,
    auth,
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

// We need parseInt() as environment variables are strings.
const asyncCaptureLimit = parseInt(process.env.BACKSTOP_ASYNC_CAPTURE_LIMIT ? process.env.BACKSTOP_ASYNC_CAPTURE_LIMIT : 4);
const asyncCompareLimit = asyncCaptureLimit * 25;

console.log(`Will capture with ${asyncCaptureLimit} browsers and compare with ${asyncCompareLimit} threads.`)

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
        "args": [
            "--no-sandbox",
            "--disable-setuid-sandbox",
            "--enable-features=NetworkService",
            "--ignore-certificate-errors"
        ]
    },
    "asyncCaptureLimit": asyncCaptureLimit,
    "asyncCompareLimit": asyncCompareLimit,
    "debug": false,
    "debugWindow": false
}
