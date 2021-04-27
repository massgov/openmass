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
// This code resizes the iFrame's height in response to a postMessage from the child iFrame



// event.data - the object that the iframe sent us
// event.origin - the URL from which the message came
// event.source - a reference to the 'window' object that sent the message
function gotResizeMessage(event)
{
	var matches = document.querySelectorAll('iframe'); // iterate through all iFrames on page
	for (i=0; i<matches.length; i++)
	{
		if( matches[i].contentWindow == event.source ) // found the iFrame that sent us a message
		{
			//matches[i].width = Number( event.data.width )	 <--- we do not do anything with the page width for now
			matches[i].height = Number( event.data.height )

			return 1;
		}
	}
}

document.addEventListener("DOMContentLoaded", function(){
  console.log('loaded parent JS')

	window.addEventListener("message", gotResizeMessage, false)

}); //on DOM ready
