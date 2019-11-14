/**
 * Think of this file as the "router" for edge traffic on mass.gov.
 *
 * It knows how to direct traffic to backend handlers.
 */
import {
  replaceErrorPages,
  isLegacyUrl,
  isEditUrl,
} from './util';

import {
  legacy,
  www,
  edit
} from "./backends";

export default class RequestHandler {
  constructor(token) {
    this.legacy = legacy();
    this.edit = edit(token);
    this.www = www(token);
  }
  async handle(request) {
    const url = new URL(request.url);

    let response;

    try {
      if(isLegacyUrl(url)) {
        response = await this.legacy(request);
      }
      else if(url.pathname.match(/^\/libraries\/?$/)) {
          response = new Response('', {
              status: 301,
              headers: {
                  location: 'https://libraries.state.ma.us/'
              }
          });
      }
      else if(isEditUrl(url)) {
        response = await this.edit(request);
      }
      else {
        response = await this.www(request);
      }
    } catch(err) {
      // If we caught an error doing the above, something
      // went wrong with our worker.  Return a 503 and move
      // on.
      console.log(err);
      response = new Response('Error', {
        status: 503,
        statusText: 'Server Error',
      });
    }

    return response
  }
}
