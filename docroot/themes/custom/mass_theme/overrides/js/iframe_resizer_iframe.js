/* Instructions
**
** Mass.gov CDN: https://mass.gov/themes/custom/mass_theme/overrides/js/iframe_resizer_iframe.js
** Add this script into the iframe source code before the closing body tag:
** <script type="text/javascript" src="https://mass.gov/themes/custom/mass_theme/overrides/js/iframe_resizer_iframe.js"></script>
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


// determine height of content on this page
function getMyHeight() {
  'use strict';
  // https://stackoverflow.com/a/11864824
  return Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
}


// send the latest page dimensions to the parent page on which this iframe is embedded
function sendDimensionsToParent(iframeDimensions_Old) {
  'use strict';
  var iframeDimensions_New = {
    width: window.innerWidth, // supported from IE9 onwards
    height: getMyHeight()
  };

  // if old width is not equal new width, or old height is not equal new height, then...
  if ((iframeDimensions_New.width !== iframeDimensions_Old.width) || (iframeDimensions_New.height !== iframeDimensions_Old.height)) {

    window.parent.postMessage(iframeDimensions_New, '*');
    iframeDimensions_Old = iframeDimensions_New;

  }

}


// on load - send the page dimensions. (we do this on load because then all images have loaded...)
window.addEventListener('load', function () {
  'use strict';
  // For debugging purposes
  // eslint-disable-next-line no-console
  console.log('loaded iframe JS: ' + document.URL);

  var iframeDimensions_Old = {
    width: window.innerWidth, // supported from IE9 onwards
    height: getMyHeight()
  };

  window.parent.postMessage(iframeDimensions_Old, '*'); // send our dimensions once, initially - so the iFrame is initialized to the correct size

  // if mutationobserver is supported by this browser
  if (window.MutationObserver) {
    // https://developer.mozilla.org/en-US/docs/Web/API/MutationObserver

    var observer = new MutationObserver(sendDimensionsToParent(iframeDimensions_Old));
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
  // if mutationobserver is NOT supported
  else {
    // check for changes on a timed interval, every 1/3 of a second
    window.setInterval(sendDimensionsToParent, 300);
  }


}); // end of window.onload
