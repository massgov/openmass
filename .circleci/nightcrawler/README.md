NightCrawler
============
Night Crawler is a tool to automate comprehensive crawls of a site on a regular basis.  Here, we use it to make sure no pages are throwing errors and using to test response time for content types. See the [Night Crawler](https://github.com/LastCallMedia/Night-Crawler) documentation for more information on the tool.

Running
-------
1. From the root directory, run `npm install` (or `yarn install`).
2. Run `ddev nightcrawler crawl`

Before querying URLs or starting the crawl, setup waits for three consecutive
successful homepage responses (HTTP 2xx), five seconds apart. This check uses the
same authentication, rate-limit bypass header, and TLS settings as the crawl.
Probes include a cache-busting query parameter and a `Cache-Control: no-cache`
header to avoid accepting a stale cached response as evidence of readiness.
HTTP errors and connection failures reset the consecutive-success count.
Redirects within the target origin are followed; redirects to another origin
do not count as readiness.

Readiness checks stop after five minutes. If the site is still unavailable,
setup fails with the last probe result and the crawl does not start. Readiness
probes are excluded from crawl results and response-time metrics. Once crawling
starts, HTTP errors are reported normally without retries. This applies to both
DDEV and CircleCI runs.

This is a startup safeguard, not a guarantee that every backend or content type
is ready. If failures persist after readiness succeeds, investigate the server
logs and concurrent Backstop load rather than relaxing the crawl's error checks.

Run the readiness checks' automated tests from the repository root with
`node --test .circleci/nightcrawler/wait_for_ready.test.js`.

You may pass the following arguments to the crawl command:
```bash
--target: The environment to target. Eg prod, test.  Default: local
--samples: The number of nodes of each content type to crawl.
--concurrency: The number of requests to allow "in-flight" at once.
```

Examples:
* `ddev nightcrawler crawl --target=cd --samples=100`: Crawl the CD environment and run through 100 of each content type.
* `ddev nightcrawler crawl --target=local`: Check all node pages in the local environment.

*Note*: When running on remote environments, Nightcrawler may attempt to verify the host's SSH signature.  If this happens, you will see something like:
```bash
The authenticity of host 'somehost.ssh.prod.acquia-sites.com (XX.XX.XX.XX)' can't be established
```
If this happens, try running any Drush command against that host first to manually verify the signature:
```bash
root@mass:/var/www/mass.local# drush @feature2 st
The authenticity of host 'massgovfeature2.ssh.prod.acquia-sites.com (35.171.11.198)' can't be established.
RSA key fingerprint is bb:94:a4:03:dd:ec:1c:93:7a:d4:e0:13:d6:23:39:3a.
Are you sure you want to continue connecting (yes/no)? yes
Warning: Permanently added 'massgovfeature2.ssh.prod.acquia-sites.com,35.171.11.198' (RSA) to the list of known hosts.
```
