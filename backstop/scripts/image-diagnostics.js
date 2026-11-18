const fs = require('node:fs');
const path = require('node:path');
const { createHash } = require('node:crypto');
const { safeUrl, safeMessage, redact } = require('./diagnostic-utils');

// Opt-in, read-only instrumentation. Backstop 6 has no after-screenshot hook,
// so wrap this page's full-page screenshot call and return its result unchanged.
module.exports = async function imageDiagnostics(page, { selector, directory, name }) {
  const report = {
    selector, readOnly: true, browser: page.context().browser()?.version(),
    screenshotBackend: process.env.PLAYWRIGHT_LEGACY_SCREENSHOT ? 'legacy' : 'default',
    platform: process.platform, architecture: process.arch,
    startedAt: Date.now(), events: [], requests: [], screenshots: [], assets: [],
    droppedEvents: 0, droppedRequests: 0
  };
  const requests = new WeakMap();
  const responses = new Map();
  const file = path.join(directory, `${name}-images.json`);
  const write = () => {
    try {
      fs.mkdirSync(directory, { recursive: true });
      fs.writeFileSync(file, JSON.stringify(report, redact, 2));
    } catch (error) { console.warn(`[Image diagnostics] ${safeMessage(error.message)}`); }
  };
  await page.exposeBinding('__backstopImageDiagnosticEvent', ({ frame }, event) => {
    if (frame !== page.mainFrame()) return;
    if (report.events.length === 200) { report.events.shift(); report.droppedEvents++; }
    report.events.push(event);
  });
  await page.addInitScript(observeImages, selector);

  page.on('request', request => {
    if (request.resourceType() !== 'image') return;
    try { if (request.frame() !== page.mainFrame()) return; }
    catch { return; }
    if (report.requests.length === 500) { report.droppedRequests++; return; }
    const record = { id: report.requests.length + 1, url: safeUrl(request.url()), startedAt: Date.now() };
    requests.set(request, record);
    report.requests.push(record);
  });
  page.on('response', response => {
    const record = requests.get(response.request());
    if (!record) return;
    record.status = response.status();
    record.respondedAt = Date.now();
    const headers = response.headers();
    record.headers = Object.fromEntries(['content-type', 'content-length', 'cache-control', 'etag', 'last-modified', 'content-dpr', 'vary', 'x-cache']
      .filter(key => headers[key] !== undefined).map(key => [key, headers[key]]));
    responses.set(record, response);
  });
  page.on('requestfinished', request => {
    const record = requests.get(request);
    if (record) { record.finishedAt = Date.now(); record.timing = request.timing(); }
  });
  page.on('requestfailed', request => {
    const record = requests.get(request);
    if (record) { record.finishedAt = Date.now(); record.error = request.failure()?.errorText; }
  });
  page.on('close', () => { report.closedAt = Date.now(); write(); });

  const snapshot = async () => {
    let timer;
    try {
      return await Promise.race([
        page.evaluate(() => window.__backstopImageDiagnosticSnapshot?.() || null),
        new Promise(resolve => { timer = setTimeout(() => resolve({ error: 'Image diagnostic snapshot timed out' }), 1500); })
      ]);
    } catch (error) { return { error: safeMessage(error.message) }; }
    finally { clearTimeout(timer); }
  };
  const saveAssets = async (snapshots, active) => {
    const urls = new Set(snapshots.flatMap(state => (state?.images || []).flatMap(image =>
      [image.src, image.currentSrc, ...image.sources.flatMap(source => source.candidates.map(candidate => candidate.url))])).map(safeUrl));
    const types = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp', 'image/gif': 'gif', 'image/avif': 'avif' };
    for (const [record, response] of responses) {
      if (!active() || report.assets.length >= 6) break;
      if (!urls.has(record.url) || !record.finishedAt || record.error || record.status >= 400 || record.assetFile) continue;
      const extension = types[record.headers['content-type']?.split(';')[0]];
      if (!extension || Number(record.headers['content-length']) > 8 * 1024 * 1024) continue;
      try {
        const body = await response.body();
        if (!active()) break;
        if (body.length > 8 * 1024 * 1024) continue;
        const filename = `${name}-image-${record.id}.${extension}`;
        fs.mkdirSync(directory, { recursive: true });
        fs.writeFileSync(path.join(directory, filename), body);
        record.assetFile = filename;
        report.assets.push({ url: record.url, file: filename, bytes: body.length, sha256: createHash('sha256').update(body).digest('hex') });
      } catch (error) { record.bodyError = safeMessage(error.message); }
    }
  };
  const screenshot = page.screenshot.bind(page);
  page.screenshot = async (options = {}) => {
    if (!options.fullPage) return screenshot(options);
    const record = { file: options.path ? path.basename(options.path) : null, before: await snapshot() };
    report.screenshots.push(record);
    let result;
    let failure;
    record.startedAt = Date.now();
    try { result = await screenshot(options); }
    catch (error) { failure = error; record.error = safeMessage(error.message); }
    record.finishedAt = Date.now();
    record.after = await snapshot();
    // Collect the bodies already received, after capture. No extra requests or
    // preliminary screenshots that could change the image-selection race.
    let timer;
    let collecting = true;
    try {
      await Promise.race([
        saveAssets([record.before, record.after], () => collecting),
        new Promise(resolve => { timer = setTimeout(() => { collecting = false; record.assetCollectionTimedOut = true; resolve(); }, 2000); })
      ]);
    } catch (error) { record.diagnosticError = safeMessage(error.message); }
    finally { collecting = false; clearTimeout(timer); write(); }
    if (failure) throw failure;
    return result;
  };
};

function observeImages(selector) {
  if (window !== window.top) return;
  const candidates = value => {
    if (value.includes('data:')) return [];
    return value.split(',').map(candidate => candidate.trim()).filter(Boolean).map(candidate => {
      const [url, ...descriptor] = candidate.split(/\s+/);
      try { return { url: new URL(url, document.baseURI).href, descriptor: descriptor.join(' ') }; }
      catch { return { url: '[invalid URL]' }; }
    });
  };
  window.__backstopImageDiagnosticSnapshot = () => ({
    at: Date.now(), pageTime: performance.now(), readyState: document.readyState,
    viewport: { width: innerWidth, height: innerHeight, outerWidth, outerHeight, devicePixelRatio, screenWidth: screen.width, screenHeight: screen.height, scrollX, scrollY, visualWidth: window.visualViewport?.width, visualHeight: window.visualViewport?.height, visualScale: window.visualViewport?.scale },
    environment: { userAgent: navigator.userAgent, hardwareConcurrency: navigator.hardwareConcurrency, deviceMemory: navigator.deviceMemory, saveData: navigator.connection?.saveData },
    images: [...document.querySelectorAll(selector)].map((image, index) => {
      const style = getComputedStyle(image);
      const rect = image.getBoundingClientRect();
      return {
        index, id: image.id, src: image.src, currentSrc: image.currentSrc,
        complete: image.complete, natural: [image.naturalWidth, image.naturalHeight],
        box: [rect.x, rect.y, rect.width, rect.height], intrinsic: [image.getAttribute('width'), image.getAttribute('height')],
        loading: image.loading, decoding: image.decoding, candidates: candidates(image.srcset), sizes: image.sizes,
        style: { objectFit: style.objectFit, objectPosition: style.objectPosition, filter: style.filter, transform: style.transform, imageRendering: style.imageRendering, visibility: style.visibility, opacity: style.opacity },
        sources: [...(image.closest('picture')?.querySelectorAll('source') || [])].map(source => ({ media: source.media, matches: !source.media || matchMedia(source.media).matches, type: source.type, sizes: source.sizes, candidates: candidates(source.srcset) }))
      };
    })
  });
  let last;
  let scheduled = false;
  const sample = reason => {
    const state = window.__backstopImageDiagnosticSnapshot();
    const signature = JSON.stringify({ viewport: state.viewport, images: state.images });
    if (signature === last) return;
    last = signature;
    window.__backstopImageDiagnosticEvent({ reason, ...state }).catch(() => {});
  };
  const schedule = () => {
    if (scheduled) return;
    scheduled = true;
    setTimeout(() => { scheduled = false; sample('mutation'); }, 0);
  };
  document.addEventListener('DOMContentLoaded', () => {
    sample('DOMContentLoaded');
    new MutationObserver(schedule).observe(document.documentElement, { subtree: true, childList: true, attributes: true, attributeFilter: ['src', 'srcset', 'sizes', 'media', 'type', 'width', 'height', 'class', 'style'] });
    setInterval(() => sample('poll'), 100);
  }, { once: true });
  document.addEventListener('load', event => { if (event.target.matches?.(selector)) sample('image-load'); }, true);
  document.addEventListener('error', event => { if (event.target.matches?.(selector)) sample('image-error'); }, true);
  window.addEventListener('resize', () => sample('resize'));
  window.visualViewport?.addEventListener('resize', () => sample('visual-viewport-resize'));
}
