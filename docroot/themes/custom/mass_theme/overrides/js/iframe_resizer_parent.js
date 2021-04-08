window.onmessage = (e) => {
  if (e.data.hasOwnProperty("iframeHeight")) {
    document.querySelector('.js-ma-responsive-iframe iframe').height = `${e.data.frameHeight}px`;
  }
};
