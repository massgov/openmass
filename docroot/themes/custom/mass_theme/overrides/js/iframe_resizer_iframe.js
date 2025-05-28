/* Instructions
**
** Mass.gov CDN: https://www.mass.gov/themes/custom/mass_theme/overrides/js/iframe_resizer_iframe.js
** Add this script into the iframe source code before the closing body tag:
** <script type="text/javascript" src="https://www.mass.gov/themes/custom/mass_theme/overrides/js/iframe_resizer_iframe.js"></script>
** This code monitors the iframe page for changes in dimensions. When change is detected, it sends send the latest dimensions to the parent page using postMessage
*/

/*

MIT License

Copyright (c) 2019 Jacob Filipp

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

var iframeDimensions_Old = {
  width: 0,
  height: 0
};

var indicator = document.createElement('div');


// send the latest page dimensions to the parent page on which this iframe is embedded
function sendDimensionsToParent() {
  'use strict';

  if (indicator.nextSibling) {
    document.body.removeChild(indicator);
    document.body.append(indicator);
  }

  var iframeDimensions_New = {
    width: window.innerWidth, // supported from IE9 onwards
    height: indicator.getBoundingClientRect().top
  };

  // if old width is not equal new width, or old height is not equal new height, then...
  if ((iframeDimensions_New.width !== iframeDimensions_Old.width) || (iframeDimensions_New.height !== iframeDimensions_Old.height)) {
    window.parent.postMessage(iframeDimensions_New, '*');
    iframeDimensions_Old = iframeDimensions_New;
  }

}

window.addEventListener('message', function (event) {
  'use strict';

  if (event.data === 'update') {
    sendDimensionsToParent();
  }
}, false);

// on load - send the page dimensions. (we do this on load because then all images have loaded...)
window.addEventListener('load', function () {
  'use strict';

  document.body.append(indicator);

  // For debugging purposes
  // eslint-disable-next-line no-console
  console.log('loaded iframe JS: ' + document.URL);
  sendDimensionsToParent();

  // Send custom scrollToIframe message on first input focus
  const firstInput = document.querySelector('input, textarea, select');
  if (firstInput) {
    firstInput.addEventListener('focus', function () {
      window.parent.postMessage({ type: 'scrollToIframe' }, '*');
    });
  }

  // if mutationobserver is supported by this browser
  if (window.MutationObserver) {
    // https://developer.mozilla.org/en-US/docs/Web/API/MutationObserver

    var observer = new MutationObserver(function (mutations) {
      sendDimensionsToParent();
    });

    var config = {
      attributes: true,
      attributeOldValue: false,
      characterData: true,
      characterDataOldValue: false,
      childList: true,
      subtree: true
    };

    observer.observe(document.body, config);

    // Sent another update after 1/2 of a second just in case
    window.setTimeout(sendDimensionsToParent, 500);

  }
  // if mutationobserver is NOT supported
  else {
    // check for changes on a timed interval, every 1/2 of a second
    window.setInterval(sendDimensionsToParent, 500);
  }


}); // end of window.onload
