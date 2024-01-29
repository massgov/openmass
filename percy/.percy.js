module.exports = {
  version: 2,
  snapshot: {
    widths: [
      320,
      1024,
      1920
    ],
    minHeight: 1024,
    percyCSS: '',
    enableJavaScript: false,
    cliEnableJavaScript: true,
    disableShadowDOM: false
  },
  discovery: {
    allowedHostnames: [],
    disallowedHostnames: [
      "www.googletagmanager.com",
      "script.crazyegg.com",
      "www.google-analytics.com",
      "js-agent.newrelic.com",
      "translate.google.com",
      "foresee.com",
      "www.youtube.com",
      "bam.nr-data.net",
      "maps.googleapis.com",
      // "9p83os0fkf.execute-api.us-east-1.amazonaws.com/v1/waittime",
      "player.vimeo.com",
      // "https://massgov.github.io/FWE/PondMaps/dfw-pond-maps-table.html"
    ],
    networkIdleTimeout: 100,
    captureMockedServiceWorker: false,
    requestHeaders: {
      "mass-bypass-rate-limit": process.env.MASS_BYPASS_RATE_LIMIT
    },
    cookies: {
      "expirationDate": 1798790400,
      "path": "/",
      "name": "im-bypass",
      "value": "true",
      "hostOnly": false,
      "httpOnly": false,
      "secure": false,
      "session": false,
      "sameSite": "Lax",
    }
  },
  upload: {
    files: '**/*.{png,jpg,jpeg}',
    ignore: '',
    stripExtensions: false
  }
}
