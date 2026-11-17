const fs = require('node:fs');
const path = require('node:path');

const captures = new WeakMap();
const transient = status => status === 408 || status === 429 || status >= 500;
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
const videoSelector = 'iframe[src*="player.vimeo.com/video/"], iframe[src*="youtube.com/embed/"], iframe[src*="youtube-nocookie.com/embed/"]';
const contentIframeSelector = '.js-ma-responsive-iframe iframe, .ma__iframe__container iframe';

function safeUrl(value) {
  try {
    const url = new URL(value);
    url.username = '';
    url.password = '';
    for (const key of url.searchParams.keys()) {
      if (/token|key|auth|password|signature|secret/i.test(key)) url.searchParams.set(key, '[redacted]');
    }
    return url.href;
  } catch { return '[invalid URL]'; }
}

function safeMessage(message) {
  return message.replace(/https?:\/\/[^\s)]+/g, safeUrl)
    .replace(/eyJ[\w-]+\.[\w-]+\.[\w-]+/g, '[redacted token]');
}

function monitor(page, scenario, viewport, isReference, config = {}) {
  const capture = {
    scenario: scenario.label, viewport: viewport.label,
    url: safeUrl(isReference && scenario.referenceUrl || scenario.url),
    expectedStatus: scenario.expectedStatus ?? 200,
    requests: [], errors: [], blocked: new Set(), stylesheets: new Map(), frameResponses: new WeakMap(),
    directory: config.diagnosticsPath || path.resolve(__dirname, '../diagnostics'),
    name: `${isReference ? 'reference' : 'test'}-${scenario.label}-${viewport.label}`.replace(/[^a-zA-Z0-9_.-]/g, '_')
  };
  captures.set(page, capture);
  page.on('response', response => {
    const request = response.request();
    if (request.isNavigationRequest()) capture.frameResponses.set(request.frame(), { status: response.status(), url: response.url() });
    if (request.resourceType() === 'stylesheet') capture.stylesheets.set(response.url(), { status: response.status(), contentType: response.headers()['content-type'] || '' });
    if (request.isNavigationRequest() && request.frame() === page.mainFrame()) {
      // A previous attempt's failed CSS must not invalidate the next document
      // while its replacement stylesheet is still loading.
      capture.stylesheets.clear();
      capture.navigation = { status: response.status(), url: safeUrl(response.url()) };
    }
    if (response.status() >= 400) capture.requests.push({ type: request.resourceType(), status: response.status(), url: safeUrl(response.url()) });
  });
  page.on('requestfailed', request => {
    if (request.isNavigationRequest()) capture.frameResponses.set(request.frame(), { error: request.failure()?.errorText });
    if (request.resourceType() === 'stylesheet') capture.stylesheets.set(request.url(), { error: request.failure()?.errorText });
    if (!capture.blocked.has(request.url())) capture.requests.push({ type: request.resourceType(), url: safeUrl(request.url()), error: request.failure()?.errorText });
  });
  // Do not serialize console arguments: Tableau tokens can appear in SDK logs.
  page.on('pageerror', error => capture.errors.push({ name: error.name, message: safeMessage(error.message) }));
  page.on('close', () => {
    if (!capture.finished) save(page, { outcome: 'readiness-failure', stage: 'navigation', error: 'Capture closed before readiness completed' });
  });
  return capture;
}

function save(page, details) {
  const capture = captures.get(page);
  if (!capture) return;
  capture.finished = true;
  fs.mkdirSync(capture.directory, { recursive: true });
  const { directory, name, blocked, finished, stylesheets, frameResponses, ...diagnostic } = capture;
  fs.writeFileSync(path.join(directory, `${name}.json`), JSON.stringify({ ...diagnostic, ...details }, (key, value) => {
    return typeof value === 'string' && /^(https?:|data:)/.test(value)
      ? (value.startsWith('data:') ? '[inline image]' : safeUrl(value)) : value;
  }, 2));
}

async function warmup(context, url, expectedStatus = 200, { attempts = 3, delay = 1000, timeout = 15000 } = {}) {
  for (let attempt = 1; attempt <= attempts; attempt++) {
    let status;
    let failure;
    try {
      const response = await context.request.get(url, { timeout });
      status = response.status();
      await response.dispose();
      if (status === expectedStatus) return;
      failure = new Error(`Expected HTTP ${expectedStatus}, received ${status} at ${safeUrl(url)}`);
    } catch (error) {
      failure = new Error(`Warmup request failed (${error.name}) at ${safeUrl(url)}`);
    }
    if (attempt === attempts || status !== undefined && !transient(status)) throw failure;
    console.warn(`[Readiness] Warmup attempt ${attempt} failed; retrying ${safeUrl(url)}`);
    await pause(delay * 2 ** (attempt - 1));
  }
}

async function navigation(page, scenario, { attempts = 3, delay = 1000 } = {}) {
  const capture = captures.get(page);
  for (let attempt = 1; ; attempt++) {
    const status = capture?.navigation?.status;
    if (status === (scenario.expectedStatus ?? 200)) return;
    if (status === undefined || !transient(status) || attempt >= attempts) {
      throw new Error(`Expected HTTP ${scenario.expectedStatus ?? 200}, received ${status ?? 'no main document response'}`);
    }
    await pause(delay * 2 ** (attempt - 1));
    await page.reload({ waitUntil: 'domcontentloaded' });
  }
}

async function styles(page, scenario) {
  if (scenario.label === 'Document') return;
  while (true) {
    const links = await page.evaluate(() => [...document.querySelectorAll('link[rel~="stylesheet"]')]
      .filter(link => !link.disabled && matchMedia(link.media || 'all').matches)
      .map(link => ({ url: link.href, sheet: !!link.sheet })));
    const responses = captures.get(page)?.stylesheets;
    for (const link of links) {
      const response = responses?.get(link.url);
      if (response && (response.error || response.status >= 400 || !/text\/css/i.test(response.contentType))) {
        throw new Error(`Stylesheet failed: ${safeUrl(link.url)} (${response.error || response.status})`);
      }
    }
    if (links.length && links.every(link => link.sheet && (/^(data:|blob:)/.test(link.url) || responses?.has(link.url)))) return;
    await page.waitForTimeout(100);
  }
}

async function scrollPage(page) {
  // Visit each viewport, rather than jumping to the footer and missing lazy
  // images/IntersectionObservers in the middle. Bound growing/infinite pages.
  for (let index = 0; index < 200; index++) {
    const bottom = await page.evaluate(() => {
      window.scrollBy(0, Math.max(100, innerHeight * 0.8));
      return scrollY + innerHeight >= document.documentElement.scrollHeight;
    });
    await page.waitForTimeout(75);
    if (bottom) return;
  }
  throw new Error('Page did not reach the bottom after 200 scroll steps');
}

async function videoPlayers(page) {
  for (const iframe of await page.locator(videoSelector).all()) {
    if (!await iframe.isVisible()) continue;
    const source = await iframe.getAttribute('src');
    await iframe.evaluate(element => { element.dataset.backstopVideoState = 'loading'; });
    try {
      await iframe.scrollIntoViewIfNeeded();
      const frame = await (await iframe.elementHandle()).contentFrame();
      if (!frame) throw new Error('Video frame was not attached');
      // An iframe load event can describe the initial blank document. Require
      // a rendered player, then wait for its preview images and fonts as well.
      await frame.waitForFunction(() => {
        const visible = element => element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden';
        return [...document.querySelectorAll('video')].some(video => visible(video) && video.readyState >= 2) ||
          [...document.querySelectorAll('button, [role="button"]')].some(button => visible(button) && /\b(play|pause)\b/i.test(button.getAttribute('aria-label') || button.title || button.textContent));
      }, null, { timeout: 30000 });
      await frame.evaluate(() => document.querySelectorAll('video').forEach(video => video.pause()));
      // The enclosing page's stylesheet monitor doesn't apply across frames.
      await assets(frame, { label: 'Document' }, 500);
      await iframe.evaluate(element => { element.dataset.backstopVideoState = 'ready'; });
    } catch (error) {
      await iframe.evaluate(element => { element.dataset.backstopVideoState = 'error'; }).catch(() => {});
      throw new Error(`Video player did not render: ${safeUrl(source)} (${error.name})`);
    }
  }
}

async function tableauSnapshots(page) {
  const frames = page.locator('.ma_tableau_container iframe');
  for (const iframe of await frames.all()) {
    if (!await iframe.isVisible() || await iframe.getAttribute('data-backstop-tableau-snapshot')) continue;
    await iframe.scrollIntoViewIfNeeded();
    const frame = await (await iframe.elementHandle()).contentFrame();
    if (!frame) throw new Error('Tableau frame was not attached');
    await frameSnapshot(iframe, frame, '.ma_tableau_container', 'backstopTableauSnapshot');
  }
}

async function frameSnapshot(iframe, frame, containerSelector, marker) {
  await assets(frame, { label: 'Document' }, 500);
  await frame.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
  // Capture cross-origin content while visible: Chromium may omit it from
  // the full-page shot even when the embedded application has finished loading.
  const bitmap = await iframe.screenshot({ animations: 'disabled', timeout: 10000 });
  await iframe.evaluate(async (element, { source, containerSelector, marker }) => {
    let owner = element;
    while (!owner.closest(containerSelector) && owner.getRootNode().host) owner = owner.getRootNode().host;
    const container = owner.closest(containerSelector);
    if (!container) throw new Error('Iframe container was not found');
    const rect = element.getBoundingClientRect();
    const image = document.createElement('img');
    image.src = source;
    image.alt = '';
    image.setAttribute('aria-hidden', 'true');
    image.dataset[marker] = 'true';
    image.style.cssText = `all: initial; position: absolute; left: 0; top: 0; width: ${rect.width}px; height: ${rect.height}px; z-index: 1; pointer-events: none;`;
    container.appendChild(image);
    await image.decode();
    const origin = image.getBoundingClientRect();
    image.style.left = `${rect.left - origin.left}px`;
    image.style.top = `${rect.top - origin.top}px`;
    // Retain the iframe in the flow, including its dimensions and selectors.
    element.style.setProperty('opacity', '0', 'important');
    element.dataset[marker] = 'true';
  }, { source: `data:image/png;base64,${bitmap.toString('base64')}`, containerSelector, marker });
}

async function contentIframeSnapshots(page) {
  let count = 0;
  const capture = captures.get(page);
  for (const iframe of await page.locator(contentIframeSelector).all()) {
    if (!await iframe.isVisible() || await iframe.evaluate(element => element.matches('iframe[src*="vimeo.com/"], iframe[src*="youtube.com/"], iframe[src*="youtube-nocookie.com/"]') || !!element.closest('.ma_tableau_container'))) continue;
    count++;
    const source = await iframe.evaluate(element => element.src);
    if (capture.blocked.has(source)) {
      await iframe.evaluate(element => { element.dataset.backstopIframeState = 'blocked'; });
      continue;
    }
    await iframe.evaluate(element => { element.dataset.backstopIframeState = 'loading'; });
    try {
      await iframe.scrollIntoViewIfNeeded();
      const frame = await (await iframe.elementHandle()).contentFrame();
      if (!frame) throw new Error('Frame was not attached');
      while (true) {
        const response = capture.frameResponses.get(frame);
        if (response?.status) await iframe.evaluate((element, status) => { element.dataset.backstopIframeStatus = status; }, String(response.status));
        if (response?.error) throw new Error(`Navigation failed (${response.error})`);
        if (response?.status >= 400) throw new Error(`HTTP ${response.status}`);
        if ((response?.status >= 200 && response.status < 300 && frame.url() !== 'about:blank') || await iframe.getAttribute('srcdoc') !== null) break;
        await page.waitForTimeout(100);
      }
      await frame.waitForFunction(() => {
        const visible = element => element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden';
        return document.readyState === 'complete' && document.body &&
          ![...document.querySelectorAll('[aria-busy="true"], [role="progressbar"]')].some(visible) &&
          (document.body.innerText.trim().length > 0 || [...document.querySelectorAll('img[src], canvas, svg, video, input, button')].some(visible));
      });
      await frameSnapshot(iframe, frame, '.js-ma-responsive-iframe, .ma__iframe__container', 'backstopIframeSnapshot');
      await iframe.evaluate(element => { element.dataset.backstopIframeState = 'ready'; });
    } catch (error) {
      await iframe.evaluate(element => { element.dataset.backstopIframeState = 'error'; }).catch(() => {});
      throw new Error(`Content iframe failed: ${safeUrl(source)} (${safeMessage(error.message)})`);
    }
  }
  return count;
}

// Serializable browser function shared by polling and failure diagnostics.
function snapshot() {
  const visible = element => element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden';
  const describe = element => element.id || element.className?.baseVal || element.className || element.tagName;
  const box = element => {
    const rect = element.getBoundingClientRect();
    return [rect.x, rect.y, rect.width, rect.height].map(number => Math.round(number * 10) / 10);
  };
  const cache = window.__backstopImageDecodes ||= new WeakMap();
  const backgrounds = window.__backstopBackgroundDecodes ||= new Map();
  const decode = (image, key, store) => {
    const source = image.currentSrc || image.src;
    let entry = store.get(key);
    if (!entry || entry.source !== source || entry.state === 'retry') {
      entry = { source, state: 'loading' };
      store.set(key, entry);
      image.decode().then(() => { entry.state = image.naturalWidth > 0 ? 'ready' : 'error'; }, () => {
        // A responsive source can change during decode. Retry while the new
        // request is pending; broken images report complete with zero width.
        entry.state = image.complete ? 'error' : 'retry';
      });
    }
    return entry.state;
  };
  const images = [...document.images].filter(image => visible(image) && (image.currentSrc || image.getAttribute('src')?.trim())).map(image => ({
    element: describe(image), source: image.currentSrc || image.src,
    state: decode(image, image, cache), complete: image.complete,
    natural: [image.naturalWidth, image.naturalHeight], box: box(image),
    fit: getComputedStyle(image).objectFit, position: getComputedStyle(image).objectPosition
  }));
  const banners = [];
  for (const video of [...document.querySelectorAll('video[poster]')].filter(visible)) {
    if (!video.poster) continue;
    let entry = backgrounds.get(video.poster);
    if (!entry) {
      const image = new Image();
      image.src = video.poster;
      entry = { image };
      backgrounds.set(video.poster, entry);
    }
    images.push({ element: `${describe(video)}:poster`, source: video.poster,
      state: decode(entry.image, entry.image, cache), box: box(video) });
  }
  for (const element of document.querySelectorAll('body *')) {
    if (!visible(element)) continue;
    for (const pseudo of [null, '::before', '::after']) {
      const style = getComputedStyle(element, pseudo);
      if (pseudo && (style.content === 'none' || style.content === 'normal')) continue;
      if (style.backgroundImage === 'none') continue;
      const sources = [...style.backgroundImage.matchAll(/url\((?:"([^"]*)"|'([^']*)'|([^)]*))\)/g)].map(match => match[1] || match[2] || match[3]).filter(Boolean);
      for (const source of sources) {
        let entry = backgrounds.get(source);
        if (!entry) {
          const image = new Image();
          image.src = source;
          entry = { image };
          backgrounds.set(source, entry);
        }
        images.push({ element: `${describe(element)}${pseudo || ''}`, source,
          state: decode(entry.image, entry.image, cache), box: box(element),
          natural: [entry.image.naturalWidth, entry.image.naturalHeight] });
      }
      if (sources.length) banners.push({ element: `${describe(element)}${pseudo || ''}`, sources, box: box(element), size: style.backgroundSize, position: style.backgroundPosition });
    }
  }
  const tableaus = [...document.querySelectorAll('.ma_tableau_container')].filter(visible).map(element => ({
    element: describe(element), state: element.querySelector('.ma_tableau_error') ? 'error' : element.dataset.tableauState || 'loading', box: box(element),
    snapshots: element.querySelectorAll('img[data-backstop-tableau-snapshot]').length
  }));
  return {
    height: document.documentElement.scrollHeight,
    fonts: document.fonts.status,
    fontErrors: [...document.fonts].filter(font => font.status === 'error').map(font => font.family),
    styles: [...document.querySelectorAll('link[rel~="stylesheet"]')].filter(link => !link.disabled && matchMedia(link.media || 'all').matches).map(link => ({ source: link.href, ready: !!link.sheet })),
    images, banners, tableaus,
    videoPlayers: [...document.querySelectorAll('iframe[data-backstop-video-state]')].map(element => ({ source: element.src, state: element.dataset.backstopVideoState, box: box(element) })),
    iframes: [...document.querySelectorAll('.js-ma-responsive-iframe iframe, .ma__iframe__container iframe')].map(element => ({ source: element.src, title: element.title, state: element.dataset.backstopIframeState || 'loading', status: element.dataset.backstopIframeStatus, snapshot: !!element.dataset.backstopIframeSnapshot, box: box(element) })),
    footer: [...document.querySelectorAll('.ma__footer-new')].map(box),
    feedback: [...document.querySelectorAll('.ma__fixed-feedback-button')].map(element => ({ display: getComputedStyle(element).display, visibility: getComputedStyle(element).visibility, box: box(element) }))
  };
}

async function assets(page, scenario, stableFor = 1000) {
  let lastSignature;
  let stableSince = Date.now();
  while (true) {
    await styles(page, scenario);
    const state = await page.evaluate(snapshot);
    const failedImage = state.images.find(image => image.state === 'error');
    if (failedImage) throw new Error(`Image failed to load or decode: ${safeUrl(failedImage.source)}`);
    if (state.tableaus.some(viz => viz.state === 'error' || viz.state === 'timeout')) throw new Error('Tableau failed to become interactive');
    if (state.fontErrors.length) throw new Error(`Fonts failed to load: ${state.fontErrors.join(', ')}`);
    const ready = state.images.every(image => image.state === 'ready' && image.complete !== false) && state.tableaus.every(viz => viz.state === 'ready') && state.fonts === 'loaded' && state.styles.every(style => style.ready);
    const signature = JSON.stringify(state);
    if (!ready || signature !== lastSignature) stableSince = Date.now();
    lastSignature = signature;
    if (ready && Date.now() - stableSince >= stableFor) return state;
    await page.waitForTimeout(200);
  }
}

async function guard(page, scenario, action) {
  const timeout = scenario.captureTimeout ?? 90000;
  page.setDefaultTimeout(timeout);
  page.setDefaultNavigationTimeout(Math.min(timeout, 30000));
  let timer;
  let stage = 'navigation';
  const step = async (name, task) => { stage = name; return task(); };
  try {
    const state = await Promise.race([
      action(step),
      new Promise((resolve, reject) => { timer = setTimeout(() => reject(new Error(`Capture readiness exceeded ${timeout}ms`)), timeout); })
    ]);
    save(page, { outcome: 'ready', state });
  } catch (error) {
    // Browser errors can include credential-bearing URLs. Persist our own
    // stage and sanitized state rather than SDK error stacks or DOM HTML.
    let diagnosticTimer;
    const state = await Promise.race([
      page.evaluate(snapshot).catch(() => null),
      new Promise(resolve => { diagnosticTimer = setTimeout(() => resolve(null), 2000); })
    ]);
    clearTimeout(diagnosticTimer);
    save(page, { outcome: 'readiness-failure', stage, state, error: safeMessage(error.message) });
    const capture = captures.get(page);
    if (capture) await page.screenshot({ path: path.join(capture.directory, `${capture.name}.png`), timeout: 2000 }).catch(() => {});
    throw new Error(`[Readiness: ${stage}] ${safeMessage(error.message)}`);
  } finally {
    clearTimeout(timer);
  }
}

module.exports = { monitor, save, warmup, navigation, styles, scrollPage, videoPlayers, tableauSnapshots, contentIframeSnapshots, snapshot, assets, guard };
