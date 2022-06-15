// Imported from https://github.com/garris/BackstopJS/blob/ac041c347dfb155859da5fb9faeada42311ff6d4/capture/engine_scripts/puppet/loadCookies.js
const fs = require('fs');

module.exports = async (page, scenario) => {
  let cookies = [];
  // Allow individual scenarios to override cookies.
  const cookiePath = scenario.cookiePath ? scenario.cookiePath : './scripts/cookies.js';

  // Read cookies - note the current working directory is the backstop directory.
  if (fs.existsSync(cookiePath)) {
    cookies = JSON.parse(fs.readFileSync(cookiePath));
  }

  // Override the domain based on what we are testing.
  const url = new URL(scenario.url);
  cookies = cookies.map(cookie => {
    if (url.host === 'mass-web') {
      cookie.domain = "mass-web";
    }
    else {
      cookie.domain = "." + url.host;
    }
    return cookie;
  });

  const setCookies = async () => {
    return Promise.all(
      cookies.map(async (cookie) => {
        await page.setCookie(cookie);
      })
    );
  };
  await setCookies();
  console.log('Cookie state restored with:', JSON.stringify(cookies, null, 2));
};
