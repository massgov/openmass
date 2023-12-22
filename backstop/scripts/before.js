module.exports = async (page, scenario, viewport, isReference, browserContext) => {
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

  await browserContext.addCookies(cookies);

  const ignoredMessages = [
    'New Relic',
    'BackstopTools have been installed'
  ];
  console.log = (message) => {
    if (typeof message === 'string' || message instanceof String) {
      ignoredMessages.some(ignore => message.includes(ignore)) ? undefined : process.stdout.write(`${message}\n`);
    }
    else {
      process.stdout.write(`${message}\n`);
    }
  };
}
