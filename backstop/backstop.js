// Determine the list of urls to use with backstop
const opt = process.argv.filter(arg=>arg.match(/^--list=/))
const file = opt.length ? opt[0].replace('--list=', '') : 'page';

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
      base = 'http://mass.local';
      break;
    case 'test':
      base = 'https://massgovstg.prod.acquia-sites.com';
      auth = getAuth();
      break;
    default:
      base = `https://massgov${target}.prod.acquia-sites.com`;
      auth = getAuth();
  }
  return {
    ...page,
    url: `${base}${page.url}?cachebuster=${Math.random().toString(36).substring(7)}`,
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
        "args": [
            "--no-sandbox",
            "--disable-setuid-sandbox",
            "--enable-features=NetworkService",
            "--ignore-certificate-errors"
        ]
    },
    "asyncCaptureLimit": 2,
    "asyncCompareLimit": 3,
    "debug": false,
    "debugWindow": false
}
