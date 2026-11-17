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

async function capture(scenario, action, size = viewport, prepare = async () => {}) {
  const context = await browser.newContext({ viewport: size });
  const page = await context.newPage();
  try {
    await prepare(page);
    await beforeCapture(page, scenario, size, false, context, { diagnosticsPath: directory });
    await page.goto(scenario.url, { waitUntil: 'domcontentloaded' });
    return await action(page);
  } finally { await context.close(); }
}

function report(label, size = viewport) {
  return JSON.parse(fs.readFileSync(path.join(directory, `test-${label}-${size.label}.json`), 'utf8'));
}

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
  await capture(scenario, page => assert.rejects(ready(page, scenario, viewport), /Image failed to load or decode/));
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

test('diagnostics remove URL credentials and token query parameters', () => {
  const page = { on() {} };
  readiness.monitor(page, { label: 'secret', url: 'https://user:password@example.com/page?token=jwt-secret&width=800' }, viewport, false, { diagnosticsPath: directory });
  readiness.save(page, { outcome: 'ready' });
  const text = JSON.stringify(report('secret'));
  assert.doesNotMatch(text, /password|jwt-secret|user:/);
  assert.match(text, /width=800/);
});

test('Vimeo and YouTube embeds load, wait for their posters, and keep analytics blocked', async () => {
  for (const provider of ['https://player.vimeo.com/video/123', 'https://www.youtube.com/embed/123']) {
    let playerRequests = 0;
    let posterRequests = 0;
    handle = (request, response) => {
      if (request.url !== '/page') return false;
      response.writeHead(200, { 'content-type': 'text/html' });
      response.end(html(`<iframe width="320" height="180" src="${provider}"></iframe><script src="https://www.googletagmanager.com/gtm.js"></script>`));
      return true;
    };
    const scenario = { label: 'video-player', url: `${origin}/page`, captureTimeout: 7000 };
    await capture(scenario, async page => {
      await ready(page, scenario, viewport);
      const frame = await (await page.locator('iframe').elementHandle()).contentFrame();
      assert.equal(await frame.locator('video').evaluate(video => video.paused), true);
      assert.equal(await page.evaluate(() => window.analyticsRan), undefined);
    }, viewport, async page => {
      // Registered before beforeCapture: its routing policy must allow the
      // actual provider request through, or this fixture is never reached.
      await page.route(`${new URL(provider).origin}/**`, async route => {
        if (route.request().url().endsWith('/poster.svg')) {
          posterRequests++;
          await new Promise(resolve => setTimeout(resolve, 400));
          return route.fulfill({ contentType: 'image/svg+xml', body: svg });
        }
        playerRequests++;
        return route.fulfill({ contentType: 'text/html', body: '<style>body{margin:0}video{width:320px;height:140px}</style><video poster="/poster.svg"></video><script>setTimeout(() => { const button = document.createElement("button"); button.setAttribute("aria-label", "Play video"); button.textContent = "Play"; document.body.appendChild(button); }, 300);</script>' });
      });
      await page.route('https://www.googletagmanager.com/**', route => route.fulfill({ contentType: 'text/javascript', body: 'window.analyticsRan = true;' }));
    });
    assert.ok(playerRequests > 0);
    assert.ok(posterRequests > 0);
    assert.equal(report(scenario.label).state.videoPlayers[0].state, 'ready');
  }
});

test('a blank video player fails readiness instead of silently passing', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<iframe width="320" height="180" src="https://player.vimeo.com/video/123"></iframe>'));
    return true;
  };
  const scenario = { label: 'blank-video', url: `${origin}/page`, captureTimeout: 1200 };
  await capture(scenario, page => assert.rejects(ready(page, scenario, viewport), /Readiness: video players/), viewport,
    page => page.route('https://player.vimeo.com/**', route => route.fulfill({ contentType: 'text/html', body: '<body>Loading</body>' })));
  assert.equal(report(scenario.label).outcome, 'readiness-failure');
  assert.equal(report(scenario.label).stage, 'video players');
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

test('regular content iframe HTTP and network failures are reported instead of captured as blank content', async () => {
  handle = (request, response) => {
    if (request.url !== '/page') return false;
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end(html('<div class="ma__iframe__container js-ma-responsive-iframe"><iframe title="Embedded document" width="300" height="200" src="https://frames.example.test/document"></iframe></div>'));
    return true;
  };
  const scenario = { label: 'failed-iframe', url: `${origin}/page`, captureTimeout: 5000 };
  for (const status of [404, 500, 'abort']) {
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
