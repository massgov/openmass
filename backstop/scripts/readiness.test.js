const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const http = require('node:http');
const { promisify } = require('node:util');
const { execFile } = require('node:child_process');
const { before: beforeTests, after, test } = require('node:test');
const { chromium } = require('playwright');
const { PNG } = require('pngjs');
const beforeCapture = require('./before');
const ready = require('./ready');
const readiness = require('./readiness');

let browser;
let server;
let origin;
let handle;
let directory;
const viewport = { label: 'desktop', width: 800, height: 600 };
const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="200"><path fill="green" d="M0 0h400v200H0z"/><path fill="blue" d="M400 0h400v200H400z"/></svg>';
const footer = '<footer class="ma__footer-new"><div class="ma__footer-new__container"><div class="ma__footer-new__navlinks">Links</div><div class="ma__footer-new__copyright">Copyright</div><div class="ma__footer-new__logo">Logo</div></div></footer>';
const html = (content = '', script = '') => `<!doctype html><html><head><link rel="stylesheet" href="/style.css"></head><body>${content}${footer}<script>${script}</script></body></html>`;

beforeTests(async () => {
  directory = fs.mkdtempSync(path.join(os.tmpdir(), 'backstop-readiness-'));
  server = http.createServer((request, response) => {
    if (handle?.(request, response)) return;
    if (request.url === '/style.css') {
      response.writeHead(200, { 'content-type': 'text/css' });
      response.end('body { margin: 0; font-family: sans-serif; } .banner { height: 100px; background: url(/background.svg) center/cover; }');
    } else if (request.url.endsWith('.svg')) {
      response.writeHead(200, { 'content-type': 'image/svg+xml' });
      response.end(svg);
    } else {
      response.writeHead(404);
      response.end('Not found');
    }
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  origin = `http://127.0.0.1:${server.address().port}`;
  browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
});

after(async () => {
  await browser?.close();
  await new Promise(resolve => server?.close(resolve));
  fs.rmSync(directory, { recursive: true, force: true });
});

async function capture(scenario, action, size = viewport, prepare = async () => {}, isReference = false) {
  const context = await browser.newContext({ viewport: size });
  const page = await context.newPage();
  try {
    await prepare(page);
    await beforeCapture(page, scenario, size, isReference, context, { diagnosticsPath: directory });
    await page.goto(scenario.url, { waitUntil: 'domcontentloaded' });
    return await action(page);
  } finally { await context.close(); }
}

function report(label, size = viewport, isReference = false) {
  return JSON.parse(fs.readFileSync(path.join(directory, `${isReference ? 'reference' : 'test'}-${label}-${size.label}.json`), 'utf8'));
}

function homepageFixture(images) {
  const renderer = fs.readFileSync(path.join(__dirname, '../../docroot/modules/custom/mass_utility/js/mass_utility.homepage_background_image.js'), 'utf8');
  return html(`<script type="application/json" data-drupal-selector="drupal-settings-json">${JSON.stringify({ unrelatedSetting: 'preserved', homepage_background_images: images })}</script>
    <script src="/jquery.js"></script><script src="/settings.js"></script>
    <style>.ma__search-banner { height:200px; position:relative; background-size:cover; background-position:center } .ma__banner-credit { position:absolute; right:0; bottom:0 }</style>
    <section class="ma__search-banner" id="GUID935283478"><style></style><span>Search homepage</span></section>`,
  `document.body.classList.add('is-front'); Math.random = () => 0.999; ${renderer}`);
}

function serveHomepageScripts(request, response) {
  const files = { '/jquery.js': '../../docroot/core/assets/vendor/jquery/jquery.min.js', '/settings.js': '../../docroot/core/misc/drupalSettingsLoader.js' };
  if (!files[request.url]) return false;
  response.writeHead(200, { 'content-type': 'application/javascript' });
  response.end(fs.readFileSync(path.join(__dirname, files[request.url]), 'utf8'));
  return true;
}

test('homepage selects the first image path across reordered environments, retains responsive images and real credits, and advances after removal', async () => {
  const alpha = { image_url: 'https://z-prod.example.test/images/alpha.svg?itok=prod', image_url_mobile: 'https://z-prod.example.test/images/alpha-mobile.svg?itok=prod', image_location: '<span>Alpha &amp; Harbor</span>', image_credit: '<strong>Actual photographer</strong>' };
  const bravo = { ...alpha, image_url: 'https://a.example.test/images/bravo.svg', image_url_mobile: 'https://a.example.test/images/bravo-mobile.svg', image_location: 'Bravo location', image_credit: 'Bravo photographer' };
  const zulu = { ...bravo, image_url: 'https://a.example.test/images/zulu.svg', image_url_mobile: 'https://a.example.test/images/zulu-mobile.svg' };
  let images;
  handle = (request, response) => {
    if (serveHomepageScripts(request, response)) return true;
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(homepageFixture(images));
    return true;
  };
  const desktop = { ...viewport, width: 1024 };
  const phone = { label: 'phone', width: 360, height: 480 };
  const stageAlpha = { ...alpha, image_url: alpha.image_url.replace('prod', 'stage').replace('itok=prod', 'itok=stage'), image_url_mobile: alpha.image_url_mobile.replace('prod', 'stage').replace('itok=prod', 'itok=stage') };
  let previous;
  for (const [records, size, expected, isReference] of [
    [[zulu, bravo, alpha], desktop, alpha, true],
    [[stageAlpha, zulu, bravo], desktop, stageAlpha, false],
    [[bravo, stageAlpha, zulu], phone, stageAlpha, false],
    [[zulu, bravo], desktop, bravo, false]
  ]) {
    images = records;
    const scenario = { label: 'Homepage', url: `${origin}/page`, captureTimeout: 5000 };
    const screenshot = await capture(scenario, async page => {
      await ready(page, scenario, size);
      assert.equal(await page.evaluate(() => drupalSettings.homepage_background_images.length), 1);
      assert.equal(await page.evaluate(() => drupalSettings.unrelatedSetting), 'preserved');
      assert.equal(await page.locator('.ma__banner-credit__image-author').textContent(), expected.image_credit.replace(/<[^>]*>/g, ''));
      if (expected !== bravo) assert.equal(await page.locator('.ma__banner-credit__image-name').textContent(), 'Alpha & Harbor');
      const selected = size.width >= 801 ? expected.image_url : expected.image_url_mobile;
      assert.ok((await page.locator('.ma__search-banner').evaluate(banner => getComputedStyle(banner).backgroundImage)).includes(selected));
      const buffer = await page.screenshot({ fullPage: true });
      const png = PNG.sync.read(buffer);
      const offset = (190 * png.width + 10) * 4;
      assert.deepEqual([...png.data.subarray(offset, offset + 4)], [0, 128, 0, 255], 'The real background must be visible');
      return buffer;
    }, size, page => page.route('https://**.example.test/**', route => route.fulfill({ contentType: 'image/svg+xml', body: svg })), isReference);
    const selection = report('Homepage', size, isReference).state.homepageBanner;
    assert.equal(selection.state, 'ready');
    assert.equal(selection.path, new URL(expected.image_url).pathname);
    if (records[0] === stageAlpha) assert.deepEqual(screenshot, previous);
    previous = screenshot;
  }
});

test('homepage reports empty image lists and a selected broken background rather than capturing a mask or another image', async () => {
  let images = [];
  handle = (request, response) => {
    if (serveHomepageScripts(request, response)) return true;
    if (request.url === '/page') { response.writeHead(200, { 'content-type': 'text/html' }); response.end(homepageFixture(images)); return true; }
    if (request.url === '/broken.svg') { response.writeHead(404); response.end(); return true; }
    return false;
  };
  const scenario = { label: 'Homepage', url: `${origin}/page`, captureTimeout: 4000 };
  await capture(scenario, page => assert.rejects(ready(page, scenario, viewport), /Readiness: homepage banner.*No homepage banner images/));
  assert.equal(report('Homepage').state.homepageBanner.state, 'error');
  images = [{ image_url: `${origin}/broken.svg`, image_url_mobile: `${origin}/broken.svg`, image_location: 'Actual location', image_credit: 'Actual credit' }];
  await capture(scenario, page => assert.rejects(ready(page, scenario, viewport), /Image failed to load or decode: .*HTTP 404/));
});

test('deterministic homepage selection does not change other scenarios or unrelated settings', async () => {
  const images = ['alpha', 'zulu'].map(name => ({ image_url: `${origin}/${name}.svg`, image_url_mobile: `${origin}/${name}-mobile.svg`, image_location: name, image_credit: `${name} photographer` }));
  handle = (request, response) => {
    if (serveHomepageScripts(request, response)) return true;
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' }); response.end(homepageFixture(images));
    return true;
  };
  const scenario = { label: 'other-banner', url: `${origin}/page`, captureTimeout: 4000 };
  await capture(scenario, async page => {
    await ready(page, scenario, viewport);
    assert.equal(await page.evaluate(() => window.__backstopHomepageBanner), undefined);
    assert.equal(await page.evaluate(() => drupalSettings.homepage_background_images.length), 2);
    assert.equal(await page.locator('.ma__banner-credit__image-author').textContent(), 'zulu photographer');
  });
});

test('warmup retries transient failures, rejects unexpected 404s, and accepts expected 404s', async () => {
  const context = await browser.newContext();
  try {
    let requests = 0;
    handle = (request, response) => {
      requests++;
      response.writeHead(requests < 3 ? 503 : 200);
      response.end();
      return true;
    };
    await readiness.warmup(context, origin, 200, { delay: 1 });
    assert.equal(requests, 3);
    requests = 0;
    handle = (request, response) => { requests++; response.writeHead(404); response.end(); return true; };
    await assert.rejects(readiness.warmup(context, origin, 200, { delay: 1 }), /received 404/);
    assert.equal(requests, 1);
    await readiness.warmup(context, origin, 404, { delay: 1 });
    handle = (request, response) => { response.writeHead(500); response.end(); return true; };
    await assert.rejects(readiness.warmup(context, origin, 404, { delay: 1 }), /received 500/);
  } finally { await context.close(); }
});

test('browser navigation is checked independently of warmup and can recover from a transient 500', async () => {
  let requests = 0;
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    requests++;
    response.writeHead(requests === 2 ? 500 : 200, { 'content-type': 'text/html' });
    response.end(html());
    return true;
  };
  const scenario = { label: 'navigation', url: `${origin}/page`, captureTimeout: 5000 };
  await capture(scenario, async page => {
    await readiness.guard(page, scenario, async () => {
      await readiness.navigation(page, scenario, { delay: 1 });
      return { status: 'checked' };
    });
  });
  assert.equal(requests, 3);
  assert.equal(report('navigation').navigation.status, 200);
});

test('delayed images, responsive sources, backgrounds and lazy loading settle reproducibly at both viewport sizes', async () => {
  handle = (request, response) => {
    if (request.url === '/delayed.svg') {
      setTimeout(() => { response.writeHead(200, { 'content-type': 'image/svg+xml' }); response.end(svg); }, 300);
      return true;
    }
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<div class="banner"></div><img id="responsive" width="160" height="40" src="/initial.svg"><div style="height:1400px"></div><img id="lazy" loading="lazy" width="160" height="40" src="/delayed.svg">', "setTimeout(() => { document.querySelector('#responsive').src = '/final.svg'; }, 500)"));
    return true;
  };
  const scenario = { label: 'assets', url: `${origin}/page`, captureTimeout: 10000 };
  for (const size of [viewport, { label: 'phone', width: 360, height: 480 }]) {
    let previous;
    for (let run = 0; run < 2; run++) {
      const screenshot = await capture(scenario, async page => {
        await ready(page, scenario, size);
        assert.equal(await page.evaluate(() => scrollY), 0);
        return page.screenshot({ fullPage: true });
      }, size);
      const result = report('assets', size);
      assert.equal(result.outcome, 'ready');
      assert.ok(result.state.images.every(image => image.state === 'ready'));
      assert.ok(result.state.images.some(image => image.source.endsWith('/final.svg')));
      assert.ok(result.state.images.some(image => image.source.endsWith('/delayed.svg')));
      assert.equal(result.state.banners[0].size, 'cover');
      if (previous) assert.deepEqual(screenshot, previous);
      previous = screenshot;
    }
  }
});

test('missing CSS is a readiness failure with HTTP diagnostics', async () => {
  handle = (request, response) => {
    if (request.url === '/page') {
      response.writeHead(200, { 'content-type': 'text/html' }); response.end(html()); return true;
    }
    if (request.url === '/style.css') { response.writeHead(404); response.end(); return true; }
    return false;
  };
  const scenario = { label: 'missing-css', url: `${origin}/page`, captureTimeout: 700 };
  await capture(scenario, page => assert.rejects(ready(page, scenario, viewport), /Readiness: stylesheets/));
  const result = report(scenario.label);
  assert.equal(result.outcome, 'readiness-failure');
  assert.ok(result.requests.some(request => request.type === 'stylesheet' && request.status === 404));
});

test('readiness leaves responsive fallback URLs, source rules, crop and layout unchanged', async () => {
  const requests = new Map();
  handle = (request, response) => {
    if (request.url.endsWith('.svg')) {
      requests.set(request.url, (requests.get(request.url) || 0) + 1);
      response.writeHead(200, { 'content-type': 'image/svg+xml' });
      response.end(request.url === '/final-wide.svg' ? svg.replace(/green/g, 'blue') : svg);
      return true;
    }
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<picture><source media="(min-width:801px)" srcset="/wide.svg 1x"><img id="hero" src="/small.svg" width="300" height="100" style="object-fit:cover;object-position:25% 50%;visibility:visible!important"></picture><img id="density" src="/small.svg" srcset="/density.svg 1x" width="160" height="40">', "setTimeout(() => { document.querySelector('source').srcset = '/final-wide.svg 1x'; }, 500);"));
    return true;
  };
  const scenario = { label: 'responsive-paint', url: `${origin}/page`, captureTimeout: 5000 };
  for (const size of [{ ...viewport, width: 1024 }, { label: 'phone', width: 360, height: 480 }]) {
    requests.clear();
    await capture(scenario, async page => {
      const original = await page.locator('#hero').boundingBox();
      await ready(page, scenario, size);
      assert.deepEqual(await page.locator('#hero').boundingBox(), original);
      assert.deepEqual(await page.locator('#hero').evaluate(image => [image.style.visibility, image.style.getPropertyPriority('visibility')]), ['visible', 'important']);
      assert.equal(await page.locator('source').getAttribute('media'), '(min-width:801px)');
      assert.equal(await page.locator('source').getAttribute('srcset'), '/final-wide.svg 1x');
      assert.equal(await page.locator('#density').getAttribute('srcset'), '/density.svg 1x');
      assert.equal(await page.locator('#density').evaluate(image => image.src), `${origin}/small.svg`);
      const expected = size.width >= 801 ? '/final-wide.svg' : '/small.svg';
      assert.equal(await page.locator('#hero').evaluate(image => image.src), `${origin}/small.svg`);
      const png = PNG.sync.read(await page.screenshot({ fullPage: true }));
      const offset = ((Math.round(original.y) + 50) * png.width + Math.round(original.x) + 50) * 4;
      assert.deepEqual([...png.data.subarray(offset, offset + 4)], size.width >= 801 ? [0, 0, 255, 255] : [0, 128, 0, 255]);
      assert.equal(requests.get(expected), 1);
    }, size);
    const image = report(scenario.label, size).state.images.find(image => image.element === 'hero');
    assert.equal(image.fallbackSource, `${origin}/small.svg`);
    assert.equal(image.fit, 'cover');
    assert.equal(image.position, '25% 50%');
  }
});

test('picture media choices survive a capture-time viewport shrink without changing image URLs, density, crop or layout', async () => {
  handle = (request, response) => {
    if (request.url.endsWith('.svg')) {
      response.writeHead(200, { 'content-type': 'image/svg+xml' });
      response.end(request.url === '/wide.svg' ? svg.replace(/green/g, 'blue') : svg);
      return true;
    }
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<picture><source media="(min-width:801px)" srcset="/wide.svg 1x"><img id="hero" src="/small.svg" width="300" height="100" style="object-fit:cover;object-position:25% 50%"></picture><picture><source media="(min-width:801px)" srcset="/double.svg 2x"><img id="density" src="/small.svg" width="160" height="40"></picture><div style="height:1800px"></div>'));
    return true;
  };
  for (const size of [{ ...viewport, width: 1024 }, { label: 'phone', width: 360, height: 480 }]) {
    const scenario = { label: 'picture-capture', url: `${origin}/page`, captureTimeout: 5000 };
    const state = page => page.evaluate(() => [...document.images].map(image => ({
      markup: image.parentElement.outerHTML, source: image.currentSrc, width: image.naturalWidth,
      box: JSON.stringify(image.getBoundingClientRect())
    })));
    let calls = 0;
    await capture(scenario, async page => {
      await ready(page, scenario, size);
      const before = await state(page);
      const png = PNG.sync.read(await page.screenshot({ fullPage: true }));
      await page.locator('img').evaluateAll(images => Promise.all(images.map(image => image.decode())));
      assert.deepEqual(await state(page), before, 'Original markup, selection and geometry must be restored');
      const offset = (50 * png.width + 50) * 4;
      assert.deepEqual([...png.data.subarray(offset, offset + 4)], size.width >= 801 ? [0, 0, 255, 255] : [0, 128, 0, 255]);
      assert.equal(calls, 1, 'No preliminary screenshots');
    }, size, async page => {
      const screenshot = page.screenshot.bind(page);
      page.screenshot = async options => {
        calls++;
        const selected = await page.locator('img').evaluateAll(images => images.map(image => [image.currentSrc, image.naturalWidth]));
        assert.deepEqual(await page.locator('source').evaluateAll(sources => sources.map(source => source.media)), [size.width >= 801 ? 'all' : 'not all', size.width >= 801 ? 'all' : 'not all']);
        await page.setViewportSize({ width: 1, height: 1 });
        await page.locator('img').evaluateAll(images => Promise.all(images.map(image => image.decode())));
        assert.deepEqual(await page.locator('img').evaluateAll(images => images.map(image => [image.currentSrc, image.naturalWidth])), selected);
        await page.setViewportSize({ width: size.width, height: size.height });
        return screenshot(options);
      };
    });
  }
});

test('picture media queries are restored after a screenshot error and viewport captures remain untouched', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<picture><source media="(min-width:1px)" srcset="/wide.svg"><img src="/small.svg" width="100" height="40"></picture>'));
    return true;
  };
  const failure = new Error('Screenshot failed');
  const result = Buffer.from('viewport screenshot');
  const scenario = { label: 'failed-picture-capture', url: `${origin}/page`, captureTimeout: 5000 };
  await capture(scenario, async page => {
    await ready(page, scenario, viewport);
    assert.equal(await page.screenshot({ fullPage: false }), result);
    await assert.rejects(page.screenshot({ fullPage: true }), error => error === failure);
    assert.equal(await page.locator('source').getAttribute('media'), '(min-width:1px)');
  }, viewport, async page => {
    page.screenshot = async options => {
      assert.equal(await page.locator('source').getAttribute('media'), options.fullPage ? 'all' : '(min-width:1px)');
      if (options.fullPage) throw failure;
      return result;
    };
  });
});

test('CI preflight requires consecutive successful stylesheet checks after a failed stylesheet', async () => {
  let stylesheets = 0;
  handle = (request, response) => {
    if (request.url === '/') { response.writeHead(200, { 'content-type': 'text/html' }); response.end(html()); return true; }
    if (request.url === '/style.css') {
      stylesheets++;
      response.writeHead(stylesheets === 1 ? 404 : 200, { 'content-type': 'text/css', 'cache-control': 'no-store' });
      response.end('body { color: black; }');
      return true;
    }
    return false;
  };
  const { stdout, stderr } = await promisify(execFile)(process.execPath, [path.join(__dirname, 'preflight.js'), '--target=tugboat', `--tugboat=${origin}`]);
  assert.equal(stylesheets, 3, stderr);
  assert.match(stderr, /Preflight attempt 1 failed/);
  assert.match(stdout, /two consecutive document and stylesheet checks/);
});

test('a broken banner image fails decoding instead of becoming a visual mismatch', async () => {
  handle = (request, response) => {
    if (request.url === '/page') { response.writeHead(200, { 'content-type': 'text/html' }); response.end(html('<img width="100" height="40" src="/broken.svg">')); return true; }
    if (request.url === '/broken.svg') { response.writeHead(404); response.end(); return true; }
    return false;
  };
  const scenario = { label: 'broken-image', url: `${origin}/page`, captureTimeout: 3000 };
  await capture(scenario, page => assert.rejects(ready(page, scenario, viewport), /Image failed to load or decode: .*HTTP 404/));
  assert.equal(report(scenario.label).outcome, 'readiness-failure');
});

test('an expected 404 succeeds, but an actual 500 cannot be captured as the 404 page', async () => {
  let requests = 0;
  let failNavigation = false;
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    requests++;
    response.writeHead(failNavigation && requests > 1 ? 500 : 404, { 'content-type': 'text/html' });
    response.end(html());
    return true;
  };
  const scenario = { label: '404', expectedStatus: 404, url: `${origin}/page`, captureTimeout: 5000 };
  await capture(scenario, page => ready(page, scenario, viewport));
  assert.equal(report('404').outcome, 'ready');
  requests = 0;
  failNavigation = true;
  await capture(scenario, async page => {
    await assert.rejects(readiness.guard(page, scenario, () => readiness.navigation(page, scenario, { delay: 1 })), /received 500/);
  });
  assert.equal(report('404').outcome, 'readiness-failure');
});

test('v2 uses FirstInteractive even when there is no spinner and preserves the original callback', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<div class="ma_tableau_container"><div id="viz">Chart</div></div>', `
      var tableau = {};
      tableau.Viz = function (element, url, options) { setTimeout(() => options.onFirstInteractive(), 700); };
      new tableau.Viz(document.querySelector('#viz'), 'fixture', { onFirstInteractive() { window.callbackCalled = true; } });
    `));
    return true;
  };
  const scenario = { label: 'tableau-v2', url: `${origin}/page`, captureTimeout: 4000 };
  await capture(scenario, async page => {
    await ready(page, scenario, viewport);
    assert.equal(await page.evaluate(() => window.callbackCalled), true);
  });
  assert.equal(report(scenario.label).state.tableaus[0].state, 'ready');
});

test('lazy v3 waits for every non-bubbling FirstInteractive event, and missing events time out', async () => {
  let interactive = true;
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<div class="ma_tableau_container"><tableau-viz>First</tableau-viz></div><div style="height:1200px"></div><div class="ma_tableau_container"><div id="lazy-viz">Second</div></div>', `
      const observer = new IntersectionObserver(entries => entries.forEach(entry => {
        if (entry.isIntersecting) {
          observer.unobserve(entry.target);
          const viz = document.createElement('tableau-viz');
          entry.target.replaceChildren(viz);
          ${interactive ? "setTimeout(() => viz.dispatchEvent(new Event('firstinteractive')), 300);" : ''}
        }
      }));
      observer.observe(document.querySelector('#lazy-viz'));
      ${interactive ? "setTimeout(() => document.querySelector('tableau-viz').dispatchEvent(new Event('firstinteractive')), 400);" : ''}
    `));
    return true;
  };
  const scenario = { label: 'tableau-v3', url: `${origin}/page`, captureTimeout: 5000 };
  await capture(scenario, page => ready(page, scenario, viewport));
  assert.deepEqual(report(scenario.label).state.tableaus.map(viz => viz.state), ['ready', 'ready']);
  interactive = false;
  scenario.captureTimeout = 1200;
  await capture(scenario, page => assert.rejects(ready(page, scenario, viewport), /Capture readiness exceeded/));
  assert.equal(report(scenario.label).outcome, 'readiness-failure');
});

test('removing a Tableau placeholder during a failed token fetch does not stop the next visualization loading', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<div id="removed" class="ma_tableau_container"><div id="first" class="ma_tableau_placeholder" data-token-url="/first"></div></div><div id="retained" class="ma_tableau_container"><div id="second" class="ma_tableau_placeholder" data-token-url="/second"></div></div>'));
    return true;
  };
  const scenario = { label: 'removed-tableau', url: `${origin}/page`, captureTimeout: 5000 };
  await capture(scenario, async page => {
    await page.evaluate(() => {
      window.Drupal = { behaviors: {}, t: text => text };
      window.drupalSettings = { massTableau: { lazy: false, settleDelay: 0 } };
      window.once = (id, selector, context) => [...context.querySelectorAll(selector)];
      window.fetch = url => url === '/first'
        ? new Promise(resolve => { window.failFirstToken = () => resolve({ ok: false, status: 503 }); })
        : Promise.resolve({ ok: true, json: async () => ({ token: 'fixture-token' }) });
      customElements.define('tableau-viz', class extends HTMLElement {
        connectedCallback() { setTimeout(() => this.dispatchEvent(new Event('firstinteractive')), 0); }
      });
    });
    await page.addScriptTag({ path: path.join(__dirname, '../../docroot/themes/custom/mass_theme/overrides/js/tableau_token_handler.js') });
    await page.evaluate(() => Drupal.behaviors.tableauTokenHandler.attach(document));
    await page.waitForFunction(() => typeof window.failFirstToken === 'function');
    await page.evaluate(() => { document.querySelector('#first').remove(); window.failFirstToken(); });
    await page.waitForFunction(() => document.querySelector('#retained').dataset.tableauState === 'ready', undefined, { timeout: 2000 });
    await page.locator('#removed').evaluate(element => element.remove());
    await ready(page, scenario, viewport);
  });
  assert.equal(report(scenario.label).outcome, 'ready');
  assert.deepEqual(report(scenario.label).errors, []);
});

test('diagnostics remove URL credentials and repeated sensitive query parameters', () => {
  const page = { on() {} };
  readiness.monitor(page, { label: 'secret', url: 'https://user:password@example.com/page?token=jwt-secret&token=second-secret&api_key=key-secret&width=800' }, viewport, false, { diagnosticsPath: directory });
  readiness.save(page, { outcome: 'ready' });
  const text = JSON.stringify(report('secret'));
  assert.doesNotMatch(text, /password|jwt-secret|second-secret|key-secret|user:/);
  assert.match(text, /width=800/);
});

test('CircleCI forced references capture the requested reference site without publishing a shared baseline', async () => {
  const yaml = require('js-yaml');
  const config = yaml.load(fs.readFileSync(path.join(__dirname, '../../.circleci/config.yml'), 'utf8'));
  const job = config.jobs.backstop_test;
  const invocation = config.workflows.backstop_compare.jobs.find(step => step.backstop_test).backstop_test;
  const forced = job.steps.find(step => step.when?.condition === '<< parameters.force-reference >>').when.steps;
  const command = forced.find(step => step.run?.name === 'Capture Backstop Reference Screenshots').run.command;
  const root = fs.mkdtempSync(path.join(directory, 'ci-reference-'));
  const cwd = path.join(root, 'backstop');
  const executable = path.join(root, 'node_modules/.bin/backstop');
  fs.mkdirSync(cwd, { recursive: true });
  fs.mkdirSync(path.dirname(executable), { recursive: true });
  fs.writeFileSync(executable, '#!/usr/bin/env node\nconsole.log(JSON.stringify(process.argv.slice(2)));\n', { mode: 0o755 });
  for (const [reference, target, tugboat] of [['prod', 'test', ''], ['test', 'tugboat', 'https://preview.example.test/?a=1&b=2']]) {
    const pipeline = { reference, target, tugboat, list: 'all', viewport: 'desktop', 'force-reference': true, cachebuster: false };
    const parameters = Object.fromEntries(Object.entries(job.parameters).map(([name, definition]) => {
      const key = invocation[name]?.match(/^<< pipeline\.parameters\.([\w-]+) >>$/)?.[1];
      return [name, key ? pipeline[key] : definition.default];
    }));
    assert.equal(parameters['force-reference'], true);
    assert.equal(parameters.reference, reference);
    const expanded = command.replace(/<< parameters\.([\w-]+) >>/g, (_, key) => parameters[key]);
    const { stdout } = await promisify(execFile)('bash', ['-eu', '-c', expanded], { cwd, env: { ...process.env, CACHEBUSTER: '' } });
    const args = JSON.parse(stdout);
    assert.ok(args.includes(`--target=${reference}`), stdout);
    assert.ok(!args.includes(`--target=${target}`), stdout);
    assert.ok(args.includes(`--tugboat=${tugboat}`), stdout);
  }
  assert.ok(!JSON.stringify(job).includes('Save Build Number'));
  // A standalone snapshot may also explicitly opt out of publishing its build.
  const snapshot = config.jobs.backstop_reference;
  const snapshotInvocation = config.workflows.backstop_snapshot.jobs.find(step => step.backstop_reference).backstop_reference;
  const pipeline = { target: 'prod', list: 'all', viewport: 'all', 'save-build': false };
  const parameters = Object.fromEntries(Object.entries(snapshot.parameters).map(([name, definition]) => {
    const key = snapshotInvocation[name]?.match(/^<< pipeline\.parameters\.([\w-]+) >>$/)?.[1];
    return [name, key ? pipeline[key] : definition.default];
  }));
  const publish = snapshot.steps.find(step => step.run?.name === 'Save Build Number').run.command
    .replace(/<< parameters\.([\w-]+) >>/g, (_, key) => parameters[key]);
  const bin = path.dirname(executable);
  fs.writeFileSync(path.join(bin, 'curl'), '#!/bin/sh\necho publishing-shared-baseline\n', { mode: 0o755 });
  const { stdout } = await promisify(execFile)('bash', ['-eu', '-c', publish], { cwd, env: { ...process.env, PATH: `${bin}:${process.env.PATH}` } });
  assert.equal(stdout, '', 'save-build=false must prevent the shared-baseline update');
});

test('homepage content masks preserve real content on other pages', async () => {
  let homepage = false;
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<div class="ma__split-columns__column"><div class="ma__rich-text">Important page content</div></div><div class="ma__featured-item__title-container"><span>Feature title</span></div><div class="ma__press-listing__secondary-item"><div class="ma__press-teaser__details">Press details</div></div>', homepage ? "document.body.classList.add('is-front');" : ''));
    return true;
  };
  const scenario = { label: 'content-masks', url: `${origin}/page`, captureTimeout: 4000 };
  await capture(scenario, async page => {
    await ready(page, scenario, viewport);
    assert.equal(await page.locator('.ma__rich-text').textContent(), 'Important page content');
    assert.equal(await page.locator('.ma__featured-item__title-container').textContent(), 'Feature title');
    assert.equal(await page.locator('.ma__press-teaser__details').textContent(), 'Press details');
  });
  homepage = true;
  await capture(scenario, async page => {
    await ready(page, scenario, viewport);
    assert.match(await page.locator('.ma__rich-text').textContent(), /News title/);
  });
});

test('video placeholders follow delayed iframe resizing without changing page layout', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<iframe width="320" height="180" src="https://player.vimeo.com/video/123"></iframe>', "setTimeout(() => { document.querySelector('iframe').height = '260'; }, 700);"));
    return true;
  };
  const scenario = { label: 'resized-video', url: `${origin}/page`, captureTimeout: 5000 };
  await capture(scenario, async page => {
    await ready(page, scenario, viewport);
    assert.deepEqual(await page.locator('[data-backstop-video-placeholder]').boundingBox(), await page.locator('iframe').boundingBox());
  }, viewport, page => page.route('https://player.vimeo.com/**', route => route.fulfill({ contentType: 'text/html', body: '<button>Play</button>' })));
});

test('video UI checks are independent of identical visual placeholders in reference and test captures', async () => {
  for (const provider of ['https://player.vimeo.com/video/123', 'https://www.youtube.com/embed/123', 'https://www.youtube-nocookie.com/embed/123']) {
    let previous;
    let mediaRequests = 0;
    handle = (request, response) => {
      if (request.url !== '/page') return false;
      response.writeHead(200, { 'content-type': 'text/html' });
      response.end(html(`<iframe width="320" height="180" src="${provider}"></iframe><script src="https://www.googletagmanager.com/gtm.js"></script>`));
      return true;
    };
    const scenario = { label: 'video-player', url: `${origin}/page`, captureTimeout: 10000 };
    for (const isReference of [true, false]) {
      const screenshot = await capture(scenario, async page => {
        const original = await page.locator('iframe').boundingBox();
        await ready(page, scenario, viewport);
        const placeholder = page.locator('[data-backstop-video-placeholder]');
        assert.equal(await placeholder.count(), 1);
        assert.deepEqual(await placeholder.boundingBox(), original);
        assert.equal(await page.evaluate(() => window.analyticsRan), undefined);
        if (isReference) {
          const frame = await (await page.locator('iframe').elementHandle()).contentFrame();
          assert.equal(await frame.evaluate(() => window.playClicked), undefined);
          assert.equal(await frame.locator('video').evaluate(video => video.paused), true);
        }
        return page.screenshot({ fullPage: true });
      }, viewport, async page => {
        await page.route(`${new URL(provider).origin}/**`, route => route.fulfill({
          status: isReference ? 200 : 401, contentType: 'text/html',
          body: isReference ? '<video preload="auto" src="https://cdn.vimeocdn.com/never-play.mp4"></video><button aria-label="Play video" onclick="window.playClicked=true">Play</button>' : '<body>Provider challenge</body>'
        }));
        await page.route('https://cdn.vimeocdn.com/**', route => { mediaRequests++; return route.fulfill({ contentType: 'video/mp4', body: '' }); });
        await page.route('https://www.googletagmanager.com/**', route => route.fulfill({ contentType: 'text/javascript', body: 'window.analyticsRan = true;' }));
      }, isReference);
      const result = report(scenario.label, viewport, isReference);
      assert.equal(result.outcome, 'ready');
      assert.equal(result.state.videoPlayers[0].state, 'placeholder');
      assert.equal(result.state.videoPlayers[0].playerCheck, isReference ? 'rendered' : 'unverified');
      if (!isReference) assert.match(result.state.videoPlayers[0].message, /HTTP 401/);
      if (previous) assert.deepEqual(screenshot, previous);
      previous = screenshot;
    }
    assert.equal(mediaRequests, 0, 'Video bytes must not be requested from the provider');
  }
});

test('a blank player is reported as unverified while its placeholder allows the page comparison', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<div class="js-ma-responsive-iframe"><iframe width="320" height="auto" src="https://player.vimeo.com/video/123"></iframe></div>'));
    return true;
  };
  const scenario = { label: 'blank-video', url: `${origin}/page`, captureTimeout: 10000 };
  await capture(scenario, page => ready(page, scenario, viewport), viewport,
    page => page.route('https://player.vimeo.com/**', route => route.fulfill({ contentType: 'text/html', body: '<body>Loading</body>' })));
  const result = report(scenario.label);
  assert.equal(result.outcome, 'ready');
  assert.equal(result.state.videoPlayers[0].state, 'placeholder');
  assert.equal(result.state.videoPlayers[0].playerCheck, 'unverified');
});

test('full-page captures include real cross-origin Tableau pixels without changing layout or viewport', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<div style="height:900px"></div>' +
      '<div class="ma_tableau_container" data-tableau-state="ready" style="margin-left:80px;width:300px"><iframe style="display:block;border:0" width="300" height="160" src="https://public.tableau.com/views/fixture/red"></iframe></div>' +
      '<div style="height:900px"></div>' +
      '<div class="ma_tableau_container" data-tableau-state="ready" style="position:relative;margin-left:120px;width:300px"><iframe style="display:block;border:0" width="300" height="800" src="https://public.tableau.com/views/fixture/blue"></iframe></div>'));
    return true;
  };
  const scenario = { label: 'tableau-pixels', url: `${origin}/page`, captureTimeout: 10000 };
  await capture(scenario, async page => {
    const rectangles = () => page.locator('.ma_tableau_container').evaluateAll(nodes => nodes.map(node => {
      const { x, y, width, height } = node.getBoundingClientRect();
      return { x, y, width, height };
    }));
    const original = await rectangles();
    await ready(page, scenario, viewport);
    assert.deepEqual(await rectangles(), original);
    assert.deepEqual(page.viewportSize(), { width: viewport.width, height: viewport.height });
    const pixels = PNG.sync.read(await page.screenshot({ fullPage: true }));
    const expected = [[200, 30, 60, 255], [30, 60, 200, 255]];
    const snapshots = page.locator('img[data-backstop-tableau-snapshot]');
    assert.equal(await snapshots.count(), 2);
    for (let index = 0; index < 2; index++) {
      const rect = await snapshots.nth(index).boundingBox();
      assert.equal(rect.x, original[index].x);
      assert.equal(rect.y, original[index].y);
      const offset = ((Math.round(rect.y) + (index === 0 ? 60 : 700)) * pixels.width + Math.round(rect.x) + 50) * 4;
      assert.deepEqual([...pixels.data.subarray(offset, offset + 4)], expected[index]);
    }
  }, viewport, page => page.route('https://public.tableau.com/**', route => route.fulfill({
    contentType: 'text/html',
    body: `<style>body{margin:0}</style><canvas width="300" height="${route.request().url().endsWith('/red') ? 160 : 800}"></canvas><img width="1" height="1"><div style="background-image:url('');width:1px;height:1px"></div><script>const context=document.querySelector('canvas').getContext('2d');context.fillStyle='white';context.fillRect(0,0,300,800);context.fillStyle='${route.request().url().endsWith('/red') ? 'rgb(200,30,60)' : 'rgb(30,60,200)'}';context.fillRect(25,20,80,750);</script>`
  })));
  assert.deepEqual(report(scenario.label).state.tableaus.map(viz => viz.snapshots), [1, 1]);
});

test('regular content iframe server and network failures are reported instead of captured as blank content', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<div class="ma__iframe__container js-ma-responsive-iframe"><iframe title="Embedded document" width="300" height="200" src="https://frames.example.test/document"></iframe></div>'));
    return true;
  };
  const scenario = { label: 'failed-iframe', url: `${origin}/page`, captureTimeout: 5000 };
  for (const status of [500, 'abort']) {
    await capture(scenario, async page => {
      await assert.rejects(ready(page, scenario, viewport), status === 'abort' ? /Navigation failed/ : new RegExp(`HTTP ${status}`));
      assert.equal(await page.locator('img[data-backstop-iframe-snapshot]').count(), 0);
    }, viewport, page => page.route('https://frames.example.test/**', route => status === 'abort'
      ? route.abort('failed') : route.fulfill({ status, contentType: 'text/html', body: '<h1>Unavailable</h1>' })));
    const result = report(scenario.label);
    assert.equal(result.outcome, 'readiness-failure');
    assert.equal(result.stage, 'content iframes');
    assert.equal(result.state.iframes[0].state, 'error');
    if (status !== 'abort') assert.equal(result.state.iframes[0].status, String(status));
  }
});

test('a rendered iframe 404 page appears in both reference and test screenshots', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<div style="height:1200px"></div><div class="ma__iframe__container js-ma-responsive-iframe" style="margin-left:30px"><iframe title="Missing document" style="display:block;border:0" width="300" height="200" src="https://frames.example.test/missing"></iframe></div>'));
    return true;
  };
  const scenario = { label: 'iframe-404', url: `${origin}/page`, captureTimeout: 8000 };
  let previous;
  for (const isReference of [true, false]) {
    const screenshot = await capture(scenario, async page => {
      await ready(page, scenario, viewport);
      const snapshot = page.locator('img[data-backstop-iframe-snapshot]');
      assert.equal(await snapshot.count(), 1);
      const rect = await snapshot.boundingBox();
      const buffer = await page.screenshot({ fullPage: true });
      const image = PNG.sync.read(buffer);
      const offset = ((Math.round(rect.y) + 120) * image.width + Math.round(rect.x) + 20) * 4;
      assert.deepEqual([...image.data.subarray(offset, offset + 4)], [240, 220, 210, 255]);
      const frame = await (await page.locator('iframe').elementHandle()).contentFrame();
      assert.equal(await frame.locator('h1').textContent(), '404 Not Found');
      return buffer;
    }, viewport, page => page.route('https://frames.example.test/**', route => route.fulfill({
      status: 404, contentType: 'text/html',
      body: '<style>body{margin:0;background:rgb(240,220,210)}</style><h1>404 Not Found</h1><p>This document does not exist.</p>'
    })), isReference);
    const result = report(scenario.label, viewport, isReference);
    assert.equal(result.outcome, 'ready');
    assert.equal(result.state.iframes[0].status, '404');
    assert.equal(result.state.iframes[0].state, 'error-page');
    assert.equal(result.state.iframes[0].snapshot, true);
    if (previous) assert.deepEqual(screenshot, previous);
    previous = screenshot;
  }
});

test('a lazy content iframe waits for its application and images and appears in the full-page screenshot', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<div style="height:1200px"></div><div class="ma__iframe__container js-ma-responsive-iframe" style="margin-left:30px"><iframe loading="lazy" title="Embedded document" style="display:block;border:0" width="300" height="800" src="https://frames.example.test/document"></iframe></div>'));
    return true;
  };
  const scenario = { label: 'ready-iframe', url: `${origin}/page`, captureTimeout: 8000 };
  await capture(scenario, async page => {
    await ready(page, scenario, viewport);
    const snapshot = page.locator('img[data-backstop-iframe-snapshot]');
    assert.equal(await snapshot.count(), 1);
    assert.deepEqual(page.viewportSize(), { width: viewport.width, height: viewport.height });
    const rect = await snapshot.boundingBox();
    const image = PNG.sync.read(await page.screenshot({ fullPage: true }));
    const offset = ((Math.round(rect.y) + 700) * image.width + Math.round(rect.x) + 50) * 4;
    assert.deepEqual([...image.data.subarray(offset, offset + 4)], [30, 150, 120, 255]);
  }, viewport, page => page.route('https://frames.example.test/**', async route => {
    if (route.request().url().endsWith('/image.svg')) {
      await new Promise(resolve => setTimeout(resolve, 1500));
      return route.fulfill({ contentType: 'image/svg+xml', body: svg });
    }
    return route.fulfill({ contentType: 'text/html', body: '<style>body{margin:0}</style><div id="app" aria-busy="true" style="height:800px">Loading<img src="/image.svg"></div><script>document.querySelector("img").onload=()=>{const app=document.querySelector("#app");app.style.background="rgb(30,150,120)";app.setAttribute("aria-busy","false");};</script>' });
  }));
  assert.equal(report(scenario.label).state.iframes[0].snapshot, true);
  assert.equal(report(scenario.label).state.iframes[0].status, '200');
});

test('iframe snapshots stay aligned when embed scripts scroll the parent during bitmap decoding', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<h1>Page header</h1><div style="height:900px"></div><div class="js-ma-responsive-iframe" style="margin-left:30px"><iframe width="300" height="800" src="https://frames.example.test/form"></iframe></div>'));
    return true;
  };
  const scenario = { label: 'scrolling-iframe', url: `${origin}/page`, captureTimeout: 7000 };
  await capture(scenario, async page => {
    await ready(page, scenario, viewport);
    assert.deepEqual(await page.locator('img[data-backstop-iframe-snapshot]').boundingBox(), await page.locator('iframe').boundingBox());
    assert.equal(await page.evaluate(() => window.scrollDuringDecode), true);
  }, viewport, async page => {
    await page.addInitScript(() => {
      const decode = HTMLImageElement.prototype.decode;
      HTMLImageElement.prototype.decode = async function () {
        await decode.call(this);
        if (this.dataset.backstopIframeSnapshot) {
          window.scrollDuringDecode = true;
          window.scrollTo({ top: 0, behavior: 'instant' });
        }
      };
    });
    await page.route('https://frames.example.test/**', route => route.fulfill({ contentType: 'text/html', body: '<style>body{margin:0;background:green}</style><form><label>Form field<input></label></form>' }));
  });
});

test('Gravity Forms waits for visible initialized fields and iframe resizing before freezing the form', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<div class="js-ma-responsive-iframe"><iframe class="js-gf-iframe" width="300" height="200" src="https://frames.example.test/gravity"></iframe></div>', "window.addEventListener('message', event => { if (event.data === 'form-ready') document.querySelector('iframe').height = '800'; });"));
    return true;
  };
  const scenario = { label: 'delayed-gravity', url: `${origin}/page`, captureTimeout: 7000 };
  await capture(scenario, async page => {
    await ready(page, scenario, viewport);
    const snapshot = page.locator('img[data-backstop-iframe-snapshot]');
    const rect = await snapshot.boundingBox();
    assert.equal(rect.height, 804);
    assert.deepEqual(rect, await page.locator('iframe').boundingBox());
    const png = PNG.sync.read(await page.screenshot({ fullPage: true }));
    const offset = ((Math.round(rect.y) + 100) * png.width + Math.round(rect.x) + 100) * 4;
    assert.deepEqual([...png.data.subarray(offset, offset + 4)], [0, 128, 0, 255]);
  }, viewport, page => page.route('https://frames.example.test/**', route => route.fulfill({ contentType: 'text/html', body: `<!doctype html><style>body{margin:0} .gform_wrapper{display:none} form{height:800px;background:green}</style>
    <div id="loading">Loading form</div><div class="gform_wrapper"><form><label>Ready field<input></label></form></div>
    <script>setTimeout(() => { document.querySelector('#loading').remove(); document.querySelector('.gform_wrapper').style.display='block'; setTimeout(() => parent.postMessage('form-ready','*'), 150); }, 2700);</script>` })));
  assert.equal(report(scenario.label).state.iframes[0].formState, 'ready');
});

test('a Gravity form that never initializes remains a readiness failure', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<div class="js-ma-responsive-iframe"><iframe class="js-gf-iframe" width="300" height="200" src="https://frames.example.test/gravity"></iframe></div>'));
    return true;
  };
  const scenario = { label: 'pending-gravity', url: `${origin}/page`, captureTimeout: 3500 };
  await capture(scenario, async page => {
    await assert.rejects(ready(page, scenario, viewport), /Readiness: content iframes/);
    assert.equal(await page.locator('img[data-backstop-iframe-snapshot]').count(), 0);
  }, viewport, page => page.route('https://frames.example.test/**', route => route.fulfill({ contentType: 'text/html', body: '<!doctype html>Loading form<div class="gform_wrapper" style="display:none"><form><input></form></div>' })));
  assert.equal(report(scenario.label).state.iframes[0].formState, 'loading');
});

test('an empty successful iframe does not pass readiness', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<div class="js-ma-responsive-iframe"><iframe width="300" height="200" src="https://frames.example.test/empty"></iframe></div>'));
    return true;
  };
  const scenario = { label: 'empty-iframe', url: `${origin}/page`, captureTimeout: 2300 };
  await capture(scenario, page => assert.rejects(ready(page, scenario, viewport), /Readiness: content iframes/), viewport,
    page => page.route('https://frames.example.test/**', route => route.fulfill({ contentType: 'text/html', body: '<body></body>' })));
  assert.equal(report(scenario.label).outcome, 'readiness-failure');
});

test('empty image sources are skipped even though the browser resolves image.src to the document URL', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<img class="ma__image" alt="" src="" width="100" height="100"><img width="10" height="10" src="   ">'));
    return true;
  };
  const scenario = { label: 'empty-image-source', url: `${origin}/page`, captureTimeout: 4000 };
  await capture(scenario, async page => {
    assert.equal(await page.locator('img.ma__image').evaluate(image => image.src), scenario.url);
    await ready(page, scenario, viewport);
  });
  assert.equal(report(scenario.label).outcome, 'ready');
  assert.ok(report(scenario.label).state.images.every(image => image.source !== scenario.url));
});

test('scenario selectors are initialized before readiness can fail, preserving the document reference filename', () => {
  const config = require('../backstop');
  const { generateTestPair } = require('backstopjs/core/util/engineTools');
  const scenario = config.scenarios.find(scenario => scenario.label === 'PersonBoardMember');
  const engineConfig = {
    _fileNameTemplate: '{configId}_{scenarioLabel}_{selectorIndex}_{selectorLabel}_{viewportIndex}_{viewportLabel}',
    _outputFileFormatSuffix: '.png', _configId: config.id,
    _bitmapsTestPath: '/tmp/test', _bitmapsReferencePath: '/tmp/reference', screenshotDateTime: 'run'
  };
  const size = { label: 'desktop', vIndex: 0 };
  // Match Backstop's engine-error path, which joins the configured selectors.
  const failure = generateTestPair(engineConfig, scenario, size, scenario.label, scenario.label, 0, scenario.selectors.join('__'));
  const success = generateTestPair(engineConfig, scenario, size, scenario.label, scenario.label, 0, 'document');
  assert.equal(failure.reference, success.reference);
  assert.equal(failure.fileName, 'regression_PersonBoardMember_0_document_0_desktop.png');
});

test('scrolling past feedback does not deadlock image-wrap captures, and intentionally hidden buttons remain hidden', async () => {
  let intentionallyHidden = false;
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html(`<div style="height:1800px">Page content</div><button class="ma__fixed-feedback-button" style="position:fixed;right:0;top:100px;${intentionallyHidden ? 'display:none' : ''}">Feedback</button>`, "window.addEventListener('scroll', () => { document.querySelector('.ma__fixed-feedback-button').style.visibility = scrollY > 50 ? 'hidden' : 'visible'; });"));
    return true;
  };
  const scenario = { label: 'InfoDetailsImageWrapRight', url: `${origin}/page`, captureTimeout: 5000 };
  for (const hidden of [false, true]) {
    intentionallyHidden = hidden;
    await capture(scenario, async page => {
      await ready(page, scenario, viewport);
      assert.equal(await page.evaluate(() => scrollY), 0);
      assert.equal(await page.locator('.ma__fixed-feedback-button').isVisible(), !hidden);
    });
    assert.equal(report(scenario.label).outcome, 'ready');
  }
});

test('embed timeouts identify the specific readiness check', async () => {
  let content;
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html(content));
    return true;
  };
  for (const [markup, stage] of [
    ['<div class="ma__caspio">Loading form</div>', 'Caspio embeds'],
    ['<div class="csv-table">Loading CSV</div>', 'CSV tables']
  ]) {
    content = markup;
    const scenario = { label: 'pending-embed', url: `${origin}/page`, captureTimeout: 700 };
    await capture(scenario, page => assert.rejects(ready(page, scenario, viewport), new RegExp(`Readiness: ${stage}`)));
    assert.equal(report(scenario.label).stage, stage);
  }
});

test('TOC readiness waits for all generated links and targets on desktop and collapsed mobile, in reference and test captures', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html(`<style>.ma__sticky-toc { display:none } @media(max-width:480px) { .ma__sticky-toc__column { display:none } }</style>
      <main id="article"><section class="ma__sticky-toc" data-parent="#article" data-sections="h2" data-min-to-show="2">
      <h2 class="ma__sticky-toc__title">Table of contents</h2><div class="ma__sticky-toc__column"><ul></ul></div></section>
      <h2 id="first">First<span class="ma__visually-hidden"> context</span></h2><h2 id="second">Second</h2><h2>Contact</h2><h2>Related</h2></main>`, `
      const toc = document.querySelector('.ma__sticky-toc');
      const list = toc.querySelector('ul');
      setTimeout(() => {
        toc.style.display = 'block';
        document.querySelector('main').classList.add('toc-parent');
        document.querySelector('#first').classList.add('sticky-toc-jump-target');
        list.innerHTML = '<li><a href="#first">First</a></li>';
      }, 300);
      setTimeout(() => {
        document.querySelector('#second').classList.add('sticky-toc-jump-target');
        list.insertAdjacentHTML('beforeend', '<li><a href="#second">Second</a></li><li><a href="#contact">Contact</a></li>');
      }, 1700);
      setTimeout(() => {
        const contact = document.querySelectorAll('main > h2')[2];
        contact.id = 'contact';
        contact.classList.add('sticky-toc-jump-target');
        window.tocFinished = true;
      }, 2700);
    `));
    return true;
  };
  for (const size of [viewport, { label: 'phone', width: 360, height: 480 }]) {
    const scenario = { label: 'delayed-toc', url: `${origin}/page`, captureTimeout: 6000 };
    const isReference = size === viewport;
    await capture(scenario, async page => {
      await ready(page, scenario, size);
      assert.equal(await page.evaluate(() => window.tocFinished), true);
      assert.equal(await page.locator('.ma__sticky-toc__column').isVisible(), size === viewport);
    }, size, async () => {}, isReference);
    const state = report(scenario.label, size, isReference).state.tableOfContents[0];
    assert.equal(state.ready, true);
    assert.equal(state.shouldRender, true);
    assert.deepEqual(state.links.map(link => link.target), ['#first', '#second', '#contact']);
  }
});

test('TOCs below the minimum, including empty ones, wait for JS to hide them without requiring links', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html(`<style>.ma__sticky-toc { display:none }</style>
      <main id="short"><section class="ma__sticky-toc" data-parent="#short" data-sections="h2" data-min-to-show="2"><div class="ma__sticky-toc__column"><ul></ul></div></section><h2>First</h2><h2>Contact</h2><h2>Related</h2></main>
      <div id="empty"><section class="ma__sticky-toc" data-parent="#empty" data-sections="h2"></section></div>
      <nav class="ma__toc--hierarchy">Server-rendered binder contents</nav>`, `
      setTimeout(() => {
        document.querySelectorAll('.ma__sticky-toc').forEach(toc => { toc.style.display = 'none'; });
        window.tocFinished = true;
      }, 1700);
    `));
    return true;
  };
  const scenario = { label: 'short-toc', url: `${origin}/page`, captureTimeout: 5000 };
  await capture(scenario, async page => {
    await ready(page, scenario, viewport);
    assert.equal(await page.evaluate(() => window.tocFinished), true);
  });
  const tocs = report(scenario.label).state.tableOfContents;
  assert.equal(tocs.length, 2);
  assert.ok(tocs.every(toc => toc.ready && !toc.shouldRender && !toc.links.length));
});

test('a TOC that never initializes fails at its named step with pending links in diagnostics', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html(`<main><section class="ma__sticky-toc" data-parent="main" data-sections="h2" style="display:none"><div class="ma__sticky-toc__column"><ul></ul></div></section><h2 id="one">One</h2><h2 id="two">Two</h2><h2 id="three">Three</h2></main>`));
    return true;
  };
  const scenario = { label: 'pending-toc', url: `${origin}/page`, captureTimeout: 800 };
  await capture(scenario, page => assert.rejects(ready(page, scenario, viewport), /Readiness: table of contents/));
  const diagnostic = report(scenario.label);
  assert.equal(diagnostic.stage, 'table of contents');
  assert.equal(diagnostic.state.tableOfContents[0].ready, false);
  assert.deepEqual(diagnostic.state.tableOfContents[0].expectedTargets, ['one', 'two', 'three']);
});

test('horizontal sticky navigation stays below the banner without covering content at different viewport heights', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html(`<style>
      .page-banner { height:200px; background:#ccc }
      .ma__sticky-nav--horizontal { position:relative; margin-top:-20px; margin-bottom:20px; height:50px }
      .ma__sticky-nav--horizontal[data-sticky=middle], .ma__sticky-nav--horizontal[data-sticky=bottom] { position:fixed; top:0; bottom:auto; margin:0 }
      .ma__sticky-nav::before { content:'false'; display:none }
      @media(max-width:480px) { .ma__sticky-nav::before { content:'true' } }
      </style><div class="pre-content"><div class="page-banner">Banner</div><div class="ma__details__sticky-nav"><nav class="ma__sticky-nav ma__sticky-nav--horizontal" data-sticky="top"><span class="ma__sticky-nav__link is-title">Table of Contents</span><a class="ma__sticky-nav__link is-active" href="#featured">Featured</a><a class="ma__sticky-nav__link" href="#last">Last section</a></nav></div></div>
      <main id="featured" style="height:1800px">Featured items</main>`, `
      window.addEventListener('scroll', () => {
        document.querySelector('nav').dataset.sticky = scrollY > 0 ? 'middle' : 'top';
        document.querySelector('a[href="#last"]').classList.add('is-active');
      });
    `));
    return true;
  };
  for (const size of [viewport, { ...viewport, height: 1100 }, { label: 'phone', width: 360, height: 480 }]) {
    const scenario = { label: 'OrgElectedOfficial', url: `${origin}/page`, captureTimeout: 5000 };
    await capture(scenario, async page => {
      await ready(page, scenario, size);
      const banner = await page.locator('.page-banner').boundingBox();
      const nav = await page.locator('nav').boundingBox();
      const content = await page.locator('main').boundingBox();
      assert.equal(nav.y, banner.y + banner.height - 20);
      assert.ok(content.y >= nav.y + nav.height);
      assert.equal(await page.evaluate(() => scrollY), 0);
      assert.equal(await page.locator('.ma__sticky-nav__link.is-active:not(.is-title)').count(), 0);
      assert.equal(await page.locator('.is-title.is-active').count(), size.width <= 480 ? 1 : 0);
    }, size);
    const nav = report(scenario.label, size).state.stickyNavigation[0];
    assert.equal(nav.position, 'relative');
    assert.equal(nav.box[1], 180);
  }
});
