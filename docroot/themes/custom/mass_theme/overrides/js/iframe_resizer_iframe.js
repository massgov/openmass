let height;
const sendPostMessage = () => {
  if (height !== document.body.offsetHeight) {
    height = document.body.offsetHeight;
    window.parent.postMessage({
      iframeHeight: height
    }, '*');
  }
}
window.onload = () => sendPostMessage();
window.onresize = () => sendPostMessage();
