NightCrawler
============
Night Crawler is a tool to automate comprehensive crawls of a site on a regular basis.  Here, we use it to make sure no pages are throwing errors and using to test response time for content types. See the [Night Crawler](https://github.com/LastCallMedia/Night-Crawler) documentation for more information on the tool.

Running
-------
1. From the root directory, run `npm install` (or `yarn install`).
2. Run `ddev nightcrawler crawl`

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
