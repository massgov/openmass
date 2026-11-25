const assert = require('node:assert/strict');
const http = require('node:http');
const fs = require('node:fs');
const vm = require('node:vm');
const { test } = require('node:test');
const waitForReady = require('./wait_for_ready');

async function serve(t, handler) {
  const server = http.createServer(handler);
  await new Promise((resolve, reject) => {
    server.once('error', reject);
    server.listen(0, '127.0.0.1', resolve);
  });
  t.after(() => {
    server.closeAllConnections();
    return new Promise(resolve => server.close(resolve));
  });
  return `http://127.0.0.1:${server.address().port}`;
}

const settings = { timeout: 2000, interval: 5, log: () => {} };

test('waits for consecutive successes, resetting after HTTP and connection errors', async t => {
  const statuses = [500, 200, 'disconnect', 200, 503, 200, 200, 200];
  let attempts = 0;
  const base = await serve(t, (req, res) => {
    const status = statuses[attempts++];
    if (status === 'disconnect') {
      req.socket.destroy();
      return;
    }
    res.writeHead(status);
    res.end();
  });

  await waitForReady(base, { timeout: 1000 }, settings);
  assert.equal(attempts, statuses.length);
});

test('uses crawl authentication and headers and bypasses cached responses', async t => {
  const requests = [];
  const base = await serve(t, (req, res) => {
    requests.push(req);
    res.end('Ready');
  });

  await waitForReady(base, {
    auth: { user: 'crawler', password: 'secret' },
    headers: { 'mass-bypass-rate-limit': 'test-bypass' },
    strictSSL: false,
    timeout: 1000,
  }, settings);

  assert.equal(requests.length, 3);
  for (const req of requests) {
    assert.equal(req.headers.authorization, `Basic ${Buffer.from('crawler:secret').toString('base64')}`);
    assert.equal(req.headers['mass-bypass-rate-limit'], 'test-bypass');
    assert.equal(req.headers['cache-control'], 'no-cache');
    assert.ok(new URL(req.url, base).searchParams.has('_nightcrawler_readiness'));
  }
});

test('fails clearly when HTTP errors persist', async t => {
  const base = await serve(t, (req, res) => {
    res.writeHead(503);
    res.end();
  });

  await assert.rejects(
    waitForReady(base, {}, { ...settings, timeout: 100, interval: 200 }),
    /did not become ready within 0\.1s\. Last probe: HTTP 503\. Crawl was not started/,
  );
});

test('rejects authentication failures instead of counting them as readiness', async t => {
  const base = await serve(t, (req, res) => {
    res.writeHead(401);
    res.end();
  });

  await assert.rejects(
    waitForReady(base, {}, { ...settings, timeout: 100, interval: 200 }),
    /Last probe: HTTP 401/,
  );
});

test('does not accept a healthy redirect destination on another origin', async t => {
  let destinationRequests = 0;
  const destination = await serve(t, (req, res) => {
    destinationRequests++;
    res.end('A different site is healthy');
  });
  const base = await serve(t, (req, res) => {
    res.writeHead(302, { Location: destination });
    res.end();
  });

  await assert.rejects(
    waitForReady(base, {}, { ...settings, timeout: 300 }),
    /Crawl was not started/,
  );
  assert.equal(destinationRequests, 0);
});

test('allows redirects within the target origin', async t => {
  let successes = 0;
  const base = await serve(t, (req, res) => {
    if (req.url === '/home') {
      successes++;
      res.end('Ready');
      return;
    }
    res.writeHead(302, { Location: '/home' });
    res.end();
  });

  await waitForReady(base, {}, settings);
  assert.equal(successes, 3);
});

test('treats malformed redirects as failed probes', async t => {
  const base = await serve(t, (req, res) => {
    res.writeHead(302, { Location: 'http://[' });
    res.end();
  });

  await assert.rejects(
    waitForReady(base, {}, { ...settings, timeout: 100, interval: 200 }),
    /Last probe: HTTP 302/,
  );
});

test('enforces the overall deadline even when a request never responds', async t => {
  const base = await serve(t, () => {});
  const started = Date.now();

  await assert.rejects(
    waitForReady(base, { timeout: 15000 }, { ...settings, timeout: 100 }),
    /did not become ready within 0\.1s.*Crawl was not started/,
  );
  assert.ok(Date.now() - started < 1000, 'Must not wait for the normal crawl request timeout');
});

// Exercise the real crawler's setup hook without querying Drupal or a remote site.
function createCrawler(waitForReady, fetchUrls) {
  const filename = require.resolve('./nightcrawler');
  const context = {
    module: { exports: {} },
    process: { argv: [], env: {} },
    require: name => {
      if (name === './wait_for_ready') return waitForReady;
      if (name === './fetch_urls') return fetchUrls;
      return require(name);
    },
  };
  vm.runInNewContext(fs.readFileSync(filename, 'utf8'), context, { filename });
  return context.module.exports;
}

test('setup waits for readiness and still reports errors during the crawl', async () => {
  let ready;
  let checking;
  const readiness = new Promise(resolve => { ready = resolve; });
  const started = new Promise(resolve => { checking = resolve; });
  let queries = 0;
  const crawler = createCrawler(() => {
    checking();
    return readiness;
  }, async () => {
    queries++;
    return [{ url: 'http://mass-web/example', group: ['node'] }];
  });

  const setup = crawler.setup();
  await started;
  assert.equal(queries, 0);
  assert.equal(crawler.queue.length, 0);
  ready();
  await setup;
  assert.equal(queries, 1);
  assert.equal(crawler.queue.length, 1);

  let requests = 0;
  crawler.driver.fetch = async () => {
    requests++;
    return { statusCode: 500, statusMessage: 'Server Error', timingPhases: { firstByte: 10 } };
  };
  const report = await crawler.work(1);
  assert.equal(requests, 1);
  assert.equal(report.data.length, 1);
  assert.equal(report.data[0].statusCode, 500);
});

test('failed readiness prevents URL queries and crawling', async () => {
  let queries = 0;
  let requests = 0;
  const crawler = createCrawler(async () => {
    throw new Error('Site did not become ready');
  }, async () => {
    queries++;
    return [];
  });
  crawler.driver.fetch = async () => { requests++; };

  await assert.rejects(crawler.crawl(1), error => String(error).includes('Site did not become ready'));
  assert.equal(queries, 0);
  assert.equal(requests, 0);
});
