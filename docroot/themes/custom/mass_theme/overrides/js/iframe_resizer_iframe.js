/**
 * @file
 * Iframe resizer iframe script.
 */
let height;
const sendPostMessage = () => {
  if (height !== document.body.offsetHeight) {
    height = document.body.offsetHeight;
    window.parent.postMessage({
      iframeHeight: height,
      iframeSrc: window.location.href
    }, '*');
  }
}
window.onload = () => sendPostMessage();
window.onresize = () => sendPostMessage();
