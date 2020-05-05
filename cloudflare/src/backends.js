
const {isStaticUrl, isAlertsUrl, isCovidURL, isMediaDownloadUrl, isValidRedirect, isFileRedirect} = require('./util');
// Used to ensure safe handling of content-disposition headers
// for media download pre-resolution.
const contentDisposition = require('content-disposition');

const RESPECT_ORIGIN = -1;

/**
 * Handler for requests to the legacy backend.
 *
 * This handler just proxies things to our legacy origin.
 *
 * @return {function(*=): Promise<Response>}
 */
export function legacy() {
  // This is where the work happens.
  return async function(request) {
    // @todo: This is where we would do legacy redirect lookups.
    return fetch(request, {
      cf: { resolveOverride: 'legacy.mass.gov',  cacheTtl: 86400}
    });
  };
}

/**
 * Handler for requests to the editor Drupal backend (edit).
 *
 * This handler adds the CDN token header to the backend request.
 *
 * @param token
 * @return {function(*=): Promise<Response>}
 */
export function edit(token) {

  // This is where the work happens.
  return async function handler(request, level = 0) {
    let backendRequest = normalizeRequest(request)
    backendRequest = addRequestHeader(backendRequest, 'mass-cdn-fwd', token)

    const url = new URL(request.url);
    let browserTTL = 1800

    // Conditionally override browser TTLs based on what is being requested.
    if(isAlertsUrl(url)) {
      browserTTL = 60
    }
    else if(isMediaDownloadUrl(url)) {
      browserTTL = 60
    }
    else if(isStaticUrl(url)) {
      browserTTL = RESPECT_ORIGIN
    }
    // Temporarily shift all COVID-19 related pages to a 1 minute browser
    // lifetime.
    else if(isCovidURL(url)) {
      browserTTL = 60;
    }

    let response = await fetch(backendRequest)

    // Attempt to pre-resolve a media download redirect by fetching the file and returning
    // it directly. When formulating the additional request,
    // base it off of the unmodified request object, not backendRequest.
    if(isMediaDownloadUrl(url) && isFileRedirect(response)) {
      const redirectUrl = new URL(response.headers.get('location'), request.url)
      response = await handler(new Request(redirectUrl, request));
      // Tack on a content-disposition header to trigger download if the response
      // was 2xx.
      if(response.status >= 200 && response.status < 300) {
        response = addDispositionHeaderToResponse(response, redirectUrl);
      }
    }

    if(browserTTL !== RESPECT_ORIGIN) {
      response = overrideBrowserTTL(response)
    }

    return response
  };
}


/**
 * Handler for requests to the public Drupal backend (www).
 *
 * This handler:
 *   * Adds the CDN token header.
 *   * Strips Cookies.
 *   * Normalizes URLs for better cacheability.
 *   * Overrides edge and browser TTLs.
 *
 * @param token
 * @return {function(*=): Promise<Response>}
 */
export function www(token) {

  // This is where the work actually happens. We take in a request
  // and return a response.
  return async function handler(request) {
    let backendRequest = normalizeRequest(request)
    backendRequest = addRequestHeader(backendRequest, 'mass-cdn-fwd', token)
    backendRequest = removeRequestHeader(backendRequest, 'cookie')

    let url = new URL(request.url);
    let browserTTL = 1800
    let edgeTTL = 1800

    // Conditionally override Edge/browser TTLs based on what is being requested.
    if(isAlertsUrl(url)) {
      edgeTTL = 60
      browserTTL = 60
    }
    else if(isMediaDownloadUrl(url)) {
      edgeTTL = 60
      browserTTL = 60
    }
    else if(isStaticUrl(url)) {
      edgeTTL = RESPECT_ORIGIN
      browserTTL = RESPECT_ORIGIN
    }
    // Temporarily shift all COVID-19 related pages to a 1 minute edge/browser
    // lifetime.
    else if(isCovidURL(url)) {
      edgeTTL = 60
      browserTTL = 60;
    }

    // Fetch from the origin, overriding edge TTL if necessary.
    let response = await fetch(backendRequest, {
      cf: edgeTTL !== RESPECT_ORIGIN ? {cacheTtl: edgeTTL} : {}
    });

    // Attempt to pre-resolve a media download redirect by fetching the file and returning
    // it directly. When formulating the additional request,
    // base it off of the unmodified request object, not backendRequest.
    if(isMediaDownloadUrl(url) && isFileRedirect(response)) {
      const redirectUrl = new URL(response.headers.get('location'), request.url)
      response = await handler(new Request(redirectUrl, request));
      // Tack on a content-disposition header to trigger download if the response
      // was 2xx.
      if(response.status >= 200 && response.status < 300) {
        response = addDispositionHeaderToResponse(response, redirectUrl);
      }
    }

    // Apply browser TTL overrides.
    if(browserTTL !== RESPECT_ORIGIN) {
      response = overrideBrowserTTL(response, browserTTL)
    }

    return stripResponseHeaders(response)
  };
}

/**
 * Request modifier to add/set a Content-Disposition header for when we
 * pre-resolve media download URLs.
 */
function addDispositionHeaderToResponse(response, url) {
  const filename = url.pathname.split('/').pop();
  if(filename && filename !== '') {
    let modifiedResponse = new Response(response.body, response);
    modifiedResponse.headers.set('Content-Disposition', contentDisposition(filename, {type: 'inline'}));
    return modifiedResponse
  }
  return response
}

/**
 * Request modifier to add/set a request header.
 */
function addRequestHeader(request, name, value) {
  const headers = new Headers(request.headers);
  headers.set(name, value);
  return new Request(request, {headers})
}

/**
 * Request modifier to remove a request header.
 */
function removeRequestHeader(request, name) {
  const headers = new Headers(request.headers);
  headers.delete(name);
  return new Request(request, {headers})
}

/**
 * Request modifier to normalize the URL of a request by removing modifiers the backend doesn't care about.
 */
function normalizeRequest(request) {
  const url = new URL(request.url)
  const newQS = new URLSearchParams(url.searchParams);
  for (let key of newQS.keys()) {
    if(key.startsWith('utm_')) {
      newQS.delete(key);
    }
  }
  url.search = newQS
  return new Request(url, request)
}

/**
 * Response modifier to remove headers that are obviously unnecessary for browsers.
 *
 * @param response
 * @return {Response}
 */
function stripResponseHeaders(response) {
  let modifiedResponse = new Response(response.body, response);
  for(let key of modifiedResponse.headers.keys()) {
    if(key.startsWith('x-ah-') || key.startsWith('x-drupal-')) {
      modifiedResponse.headers.delete(key);
    }
  }
  return modifiedResponse
}

/**
 * Modify the browser TTL for a response.
 *
 * By default, page rules will only EXTEND TTLs it receives from the origin.
 * We need to shorten them, since our max-age is very high, so we can't use
 * page rules for this.
 *
 * @see https://support.cloudflare.com/hc/en-us/articles/200168366-What-does-browser-cache-expire-TTL-mean-
 *
 * @param {Response} response
 * @param number maxAge
 * @return {Response}
 */
function overrideBrowserTTL(response, maxAge) {
  const modifiedResponse = new Response(response.body, response);
  const cacheControl = modifiedResponse.headers.get('cache-control')

  // Limit the max age before sending to the browser.  Rather than get involved
  // with all the hairy details of cache control, we are just going to reset the
  // max-age number and leave the rest alone.
  if(cacheControl && cacheControl.match(/(^|,| )max-age=\d+/)) {
    modifiedResponse.headers.set('cache-control', cacheControl.replace(/(^|,| )max-age=\d+/, `$1max-age=${maxAge}`))
  }

  return modifiedResponse
}
