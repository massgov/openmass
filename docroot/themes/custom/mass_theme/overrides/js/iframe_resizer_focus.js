iFrameResize({
  log: false,
  checkOrigin: false,
  messageCallback: function (messageData) {
    const { message, iframe } = messageData;

    if (message.type === 'scrollToFocus') {
      // iframe position in the parent page
      const iframeTop = iframe.getBoundingClientRect().top + window.scrollY;

      // message.offset is the offset inside the iframe
      const scrollTarget = iframeTop + message.offset;

      window.scrollTo({
        top: scrollTarget,
        behavior: 'smooth'
      });
    }
  }
}, '.js-ma-responsive-iframe iframe');