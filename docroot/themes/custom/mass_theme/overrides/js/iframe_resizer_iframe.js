/**
 * @file
 * Iframe resizer iframe script.
 * @see https://jacobfilipp.com/iframe-height-autoresize-crossdomain/
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


// Add this script into the page that will appear <b>inside an iFrame</b>
// This code monitors the page for changes in size. When change is detected, it sends send the latest size to the parent page using postMessage.
var iframeDimensions_Old = '';

// Determine height of content on this page.
function getMyHeight() {
  'use strict';

  // https://stackoverflow.com/a/11864824
  return Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
}


// Send the latest page dimensions to the parent page on which this iframe is embedded.
function sendDimensionsToParent() {
  'use strict';

  var iframeDimensions_New = {
    // Supported from IE9 onwards.
    width: window.innerWidth,
    height: getMyHeight()
  };

  // If old width is not equal new width, or old height is not equal new height, then...
  if ((iframeDimensions_New.width !== iframeDimensions_Old.width) || (iframeDimensions_New.height !== iframeDimensions_Old.height)) {

    window.parent.postMessage(iframeDimensions_New, '*');
    iframeDimensions_Old = iframeDimensions_New;

  }

}


// On load - send the page dimensions. (we do this on load because then all images have loaded...)
window.addEventListener('load', function () {
  'use strict';

  iframeDimensions_Old = {
    // Supported from IE9 onwards.
    width: window.innerWidth,
    height: getMyHeight()
  };

  // Send our dimensions once, initially - so the iFrame is initialized to the correct size.
  window.parent.postMessage(iframeDimensions_Old, '*');

  // If mutationobserver is supported by this browser.
  if (window.MutationObserver) {
    // https://developer.mozilla.org/en-US/docs/Web/API/MutationObserver.
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
  // If mutationobserver is NOT supported.
  else {
    // Check for changes on a timed interval, every 1/3 of a second.
    window.setInterval(sendDimensionsToParent, 300);
  }

// End of window.onload.
});
