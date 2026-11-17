const request = require('request-promise');

/**
 * Wait for stable HTTP readiness before collecting crawl results.
 * The deadline includes requests and delays, even if a request hangs.
 */
async function waitForReady(base, requestOptions, {
  timeout = 300000,
  interval = 5000,
  requiredSuccesses = 3,
  log = console.log,
} = {}) {
  const deadline = Date.now() + timeout;
  let successes = 0;
  let lastResult = 'No response received';

  log(`Waiting for ${base} to become ready (up to ${timeout / 1000}s).`);

  while (Date.now() < deadline) {
    // Avoid accepting an old cached homepage as evidence of readiness.
    const url = new URL(base);
    url.searchParams.set('_nightcrawler_readiness', Date.now().toString());
    const remaining = deadline - Date.now();
    const probe = request({
      ...requestOptions,
      uri: url.toString(),
      headers: {
        ...requestOptions.headers,
        'Cache-Control': 'no-cache',
      },
      timeout: Math.min(requestOptions.timeout || 15000, remaining),
      resolveWithFullResponse: true,
      simple: false,
      // Another site's successful response cannot establish this site's readiness.
      followRedirect: response => {
        const location = response.headers.location;
        const currentUrl = response.request.uri.href;
        return !location || (URL.canParse(location, currentUrl) &&
          new URL(location, currentUrl).origin === url.origin);
      },
    });
    let timer;

    try {
      const response = await Promise.race([
        probe,
        new Promise((resolve, reject) => {
          timer = setTimeout(() => {
            reject(new Error('Readiness deadline exceeded'));
            probe.abort();
          }, remaining);
        }),
      ]);
      lastResult = `HTTP ${response.statusCode}`;
      successes = response.statusCode >= 200 && response.statusCode < 300
        ? successes + 1
        : 0;
    }
    catch (error) {
      successes = 0;
      lastResult = error.message;
    }
    finally {
      clearTimeout(timer);
    }

    if (successes === requiredSuccesses && Date.now() < deadline) {
      log(`HTTP readiness confirmed for ${base}.`);
      return;
    }

    log(`Readiness: ${lastResult} (${successes}/${requiredSuccesses} consecutive successes).`);
    await new Promise(resolve => setTimeout(resolve, Math.max(0, Math.min(interval, deadline - Date.now()))));
  }

  throw new Error(`Site ${base} did not become ready within ${timeout / 1000}s. Last probe: ${lastResult}. Crawl was not started.`);
}

module.exports = waitForReady;
