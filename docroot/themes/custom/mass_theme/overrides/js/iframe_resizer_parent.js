/**
 * @file
 * Iframe resizer parent script.
 * @see https://jacobfilipp.com/iframe-height-autoresize-crossdomain/
 */

function gotResizeMessage(event) {
  'use strict';

  var matches = document.querySelectorAll('iframe');
  for (var i = 0; i < matches.length; i++) {
    if (matches[i].contentWindow === event.source) {
      matches[i].height = Number(event.data.height);

      return 1;
    }
  }
}

document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  window.addEventListener('message', gotResizeMessage, false);
});
