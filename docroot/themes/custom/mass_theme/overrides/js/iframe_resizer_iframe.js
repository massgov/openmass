let height;
const sendPostMessage = () => {
  if (height !== document.body.offsetHeight) {
    height = document.body.offsetHeight;
    window.parent.postMessage({
      iframeHeight: height
    }, '*');
    console.log(height) // check the message is being sent correctly
  }
}
window.onload = () => sendPostMessage();
window.onresize = () => sendPostMessage();
