// Chromium can briefly resize a page to 1x1 during a full-page screenshot.
// Hold <picture> media-query choices at the real viewport so its sources
// cannot switch to a mobile fallback inside the capture operation.
// Reproducer: https://github.com/schloerke/chrome-devtools-protocol-screenshot-bug
// Backstop 6 has no after-capture hook, so wrap capture to guarantee cleanup.
module.exports = function responsiveImageCapture(page) {
  const screenshot = page.screenshot.bind(page);
  page.screenshot = async (options = {}) => {
    if (!options.fullPage) return screenshot(options);
    const held = await page.evaluateHandle(() => {
      const records = [];
      for (const source of document.querySelectorAll('picture > source[media]')) {
        const media = source.getAttribute('media');
        if (!media.trim()) continue;
        records.push({ source, media });
        source.media = matchMedia(media).matches ? 'all' : 'not all';
      }
      return records;
    });
    let failure;
    try {
      return await screenshot(options);
    } catch (error) { failure = error; throw error; }
    finally {
      try {
        await held.evaluate(records => {
          for (const { source, media } of records) source.setAttribute('media', media);
        });
      } catch (error) { if (!failure) throw error; }
      finally { await held.dispose().catch(() => {}); }
    }
  };
};
