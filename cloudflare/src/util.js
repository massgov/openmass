const editDomains = ['edit.mass.gov', 'edit.stage.mass.gov', 'editcf.digital.mass.gov'];


export function isEditUrl(url) {
  return editDomains.includes(url.hostname);
}


const staticExtensions = [
  'woff',
  'woff2',
  'ttf',
  'eot',
  'gif',
  'png',
  'jpg',
  'jpeg',
  'svg',
  'js',
  'css',
  'ico',
];
const staticRegexp = new RegExp(`\.(${staticExtensions.join('|')})\$`)

export function isStaticUrl(url) {
  return url.pathname.startsWith('/files') || url.pathname.match(staticRegexp)
}

export function isAlertsUrl(url) {
  return url.pathname.startsWith('/alerts') || url.pathname.startsWith('/jsonapi/node/alert')
}

export function isDrupalResponse(response) {
  const generator = response.headers.get('X-Generator')
  return generator && generator.startsWith('Drupal')
}

export function isMediaDownloadUrl(url) {
  return url.pathname.match(/^\/media\/\d+\/download$/) || url.pathname.match(/^\/doc\/[^\/]+\/download$/)
}

export function isCovidURL(url) {
  return url.pathname.match(/covid/);
}

export function isValidRedirect(response) {
  return response.status === 301 && response.headers.has('location');
}

/**
 * Check whether a response redirects the user to a file in the files directory.
 *
 * @param response
 * @return {boolean}
 */
export function isFileRedirect(response) {
  if(isValidRedirect(response)) {
    // Parse the location header, allowing both relative and absolute forms to be used.
    const redirect = new URL(response.headers.get('location'), 'http://example.com');
    return redirect.pathname.startsWith('/files');
  }
  return false;
}
