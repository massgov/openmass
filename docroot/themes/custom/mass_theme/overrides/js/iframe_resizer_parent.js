/**
 * @file
 * Iframe resizer parent script.
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

// Add this script to the <b>parent page on which your iFrame is embedded</b>
// This code resizes the iFrame's height in response to a postMessage from the child iFrame.
//
// event.data - the object that the iframe sent us.
// event.origin - the URL from which the message came.
// event.source - a reference to the 'window' object that sent the message.
function gotResizeMessage(event) {
  'use strict';

  // Iterate through all iFrames on page.
  var matches = document.querySelectorAll('iframe');
  for (var i = 0; i < matches.length; i++) {
    // Found the iFrame that sent us a message.
    if (matches[i].contentWindow === event.source) {

      // Matches[i].width = Number( event.data.width )	 <--- we do not do anything with the page width for now.
      matches[i].height = Number(event.data.height);

      return 1;
    }
  }
}

// On DOM ready.
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  window.addEventListener('message', gotResizeMessage, false);
});
