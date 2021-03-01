/**
 * @file
 * Iframe resizer iframe script.
 * @see https://jacobfilipp.com/iframe-height-autoresize-crossdomain/
 */

function getDimensions() {
  'use strict';

  var height = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);

  return {
    width: window.innerWidth,
    height: height
  };
}

function sendDimensionsToParent() {
  'use strict';


  window.parent.postMessage(getDimensions, '*');
}

window.addEventListener('load', function () {
  'use strict';

  window.parent.postMessage(getDimensions, '*');

  if (window.MutationObserver) {
    var observer = new MutationObserver(sendDimensionsToParent);
    var config = {
      attributes: true,
      attributeOldValue: false,
      characterData: true,
      characterDataOldValue: false,
      childList: true,
      subtree: true
    };

    observer.observe(document.body, config);
  }
  else {
    window.setInterval(sendDimensionsToParent, 300);
  }
});
