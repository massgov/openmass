const {crawl, test, after} = require('lastcall-nightcrawler');
const expect = require('expect');
const {fetchTypes, fetchSamplesForType} = require('./fetch_urls');


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
    base = 'http://mass.local';
    alias = '@self';
    break;
  case 'test':
    base = 'https://edit.stage.mass.gov';
    alias = '@test';
    auth = getAuth();
    break;
  default:
    base = `https://massgov${target}.prod.acquia-sites.com`;
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
  return [
    process.env.LOWER_ENVIR_AUTH_USER.replace(/(^["']|["']$)/g, ''),
    process.env.LOWER_ENVIR_AUTH_PASS.replace(/(^["']|["']$)/g, '')
  ].join(':')
}

function average(points) {
  if(points.length === 0) {
    return 0;
  }
  return points.reduce((c, t) => c + t, 0) / points.length;
}

const expectedTimes = {
  default: 2250,
  // Set time expectations for each content type here:
  // info_details: 1000
};

module.exports = crawl('Mass.gov', async function() {
  const totals = new Map();

  test('Each URL should return a 2xx response code', function(unit) {
    // Collect response time for each request.
    const groupTotals = totals.get(unit.request.group) || [];
    groupTotals.push(unit.response.time);
    totals.set(unit.request.group, groupTotals);

    // Assert that the response is not a server error.
    expect(unit.response.statusCode).toBeLessThan(500);
  });

  // Fetch all content types from the system.
  const types = await fetchTypes(alias);

  // Assert average response time.
  for(const type of types) {
    const expectation = expectedTimes.hasOwnProperty(type) ? type : expectedTimes.default;
    after(`Average response time for ${type} should be < ${expectation}`, function() {
      const groupTimes = totals.get(`node:${type}`) || [];
      expect(average(groupTimes)).toBeLessThan(expectation);
    })
  }

  // Return an async generator that fetches the URLs to crawl as we need them.
  return async function* () {
    for(const type of types) {
      const urls = await fetchSamplesForType(alias, type, sampleSize);
      for(const url of urls) {
        yield {
          url: `${base}${url}`,
          group: ['node', `node:${type}`],
          options: {auth, timeout: 15000}
        }
      }
    }
  }();
});

