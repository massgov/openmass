
var Crawler = require('lastcall-nightcrawler');
var RequestDriver = Crawler.drivers.request;
const fetchAllUrlsFromDrush = require('./fetch_urls')
const metrics = require('./metrics');

/**
 * There are two undocumented flags you can use with this
 * configuration:
 *
 * --target - Sets the target environment (eg: test)
 * --samples - Sets the number of each node type to crawl.
 */
let target = 'local';
let sampleSize = 999999999;

var _target, _sampleSize;

if(_target = parseOpt('target')) {
  target = _target;
}
if(_sampleSize = parseOpt('samples')) {
  sampleSize = parseInt(_sampleSize);
}

let base = '';
let alias = '';
let auth = false;

switch(target) {
  case 'prod':
    base = 'https://www.mass.gov';
    alias = '@prod';
    break;
  case 'local':
    base = 'http://mass-web';
    alias = '@self';
    break;
  case 'test':
    base = 'https://stage.mass.gov';
    alias = '@test';
    auth = getAuth();
    break;
  default:
    base = `https://${target}.edit.mass.gov`;
    alias = `@${target}`
    auth = getAuth();
}

function parseOpt(optname) {
  const matches = process.argv.filter(arg => arg.indexOf(`--${optname}=`) === 0);
  if(matches.length) {
    return matches[0].replace(`--${optname}=`, '');
  }
}

function getAuth() {
  // Trim leading and trailing quotes off of the auth variables.
  // This works around docker-compose's handling of environmnent
  // variables with quotes.
  if(!process.env.LOWER_ENVIR_AUTH_USER) {
    throw new Error('LOWER_ENVIR_AUTH_USER environment variable has not been set. Please set it before attempting to run nightcrawler in this environment.')
  }
  return {
    user: process.env.LOWER_ENVIR_AUTH_USER.replace(/(^["']|["']$)/g, ''),
    password: process.env.LOWER_ENVIR_AUTH_PASS.replace(/(^["']|["']$)/g, '')
  };
}

var driver = new RequestDriver({
  strictSSL: false,
  headers: {
    "mass-bypass-rate-limit": process.env.MASS_BYPASS_RATE_LIMIT,
  },
  // This number is arbitrary - we report performance statistics during
  // the crawl - we just want to avoid a failure here due to timeout, which
  // occurs for the first pageview on a cold cache.
  timeout: 15000,
  auth: auth,
});
var crawler = new Crawler('Mass.gov', driver);

/**
 * Queue requests by querying the database.
 */
crawler.on('setup', function() {
  return fetchAllUrlsFromDrush(base, alias, sampleSize)
    .then(function(requests) {
      requests.forEach(function(request) {
        crawler.enqueue(request);
      })
    })
});

// Collect additional data about each response.
crawler.on('response.success', function(response, data) {
  data.statusMessage = response.statusMessage;
});

/**
 * Analyze the data once it's been collected.
 */
crawler.on('analyze', function(report, analysis) {
  const responses = report.data;

  analysis.addMetric('time', metrics.responseTime(responses, 'Average TTFB', 1000));
  analysis.addMetric('500s', metrics.serverErrors(responses, '500 Requests', 0));
  collectGroups(responses).forEach(function(group) {
    const groupResponses = responses.filter(getGroupFilter(group))
    analysis.addMetric(group+'.time', metrics.responseTime(groupResponses, 'Average TTFB: ' + group, 2000))
    analysis.addMetric(group+'.500s', metrics.serverErrors(groupResponses, '500 Requests: ' + group, 0));
  })
  responses.forEach(function(response) {
    var level = response.statusCode > 499 ? 2 : 0;
    analysis.addResult(response.url, level, response.backendTime, response.statusMessage);
  });
});

function getGroupFilter(group) {
  return function(request) {
    return request.group.indexOf(group) !== -1
  }
}

function collectGroups(requests) {
  return requests.reduce(function(groups, request) {
    request.group.forEach(function(group) {
      if(groups.indexOf(group) === -1) {
        groups.push(group)
      }
    })
    return groups;
  }, []);
}

module.exports = crawler;

