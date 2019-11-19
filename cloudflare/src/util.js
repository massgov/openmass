const legacyPrefixes = [
  'anf',
  'portal',
  'governor',
  'eopss',
  'eohhs',
  'ocabr',
  'lwd',
  'hed',
  'treasury',
  'ago',
  'mtrs',
  'berkshireda',
  'capeda',
  'cjc',
  'comptroller',
  'dor',
  'dppc',
  'mdaa',
  'elders',
  'essexsheriff',
  'essexda',
  'edu',
  'eea',
  'hdc',
  'informedma',
  'ig',
  'women',
  'courts',
  'mova',
  'msa',
  'massworkforce',
  'childadvocate',
  'pca',
  'auditor',
  'ethics',
  'veterans',
  'cgi-bin',
  'itdemployee',
  'alert',
  'mcad',
  'perac',
  'envir',
  'legis',
  'bb',
  'abcc',
  'da',
  'export',
  'cgly',
  'srbtf',
  'chapter55',
  'opendata',
  'norfolkda',
  'daplymouth',
  'courts_reports',
  'ClientsSecurityBoard',
  'massdot',
  'osc',
  'bb2',
];

const legacyRegex = new RegExp('^/(' + legacyPrefixes.join('|') + ')(/|$)')
// const legacyRegex = new RegExp('^/(' + legacyPrefixes.join('|') + ')')

export function isLegacyUrl(url) {
  return legacyRegex.test(url.pathname);
}

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

export function isValidRedirect(response) {
  return response.status === 301 && response.headers.has('location');
}
