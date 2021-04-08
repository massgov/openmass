/**
 * @file
 * Iframe resizer parent script.
 */
window.onmessage = (e) => {
  document.querySelectorAll('.js-ma-responsive-iframe').forEach((item) => {
    const iframe = item.querySelector('iframe');
    if (e.data.hasOwnProperty("iframeHeight")) {
      if (e.data.iframeSrc === iframe.src) {
        iframe.height = `${e.data.iframeHeight + 30}px`;
      }
    }
  });
};
