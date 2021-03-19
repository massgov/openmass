# Security

Mass.gov is a vital resource and we protect it vigorously. Please familiarize yourself with these resources:

- [Cloudflare](https://www.cloudflare.com/learning/cloud/what-is-cloud-security/). 
  - Our first layer of defense is the Cloudflare Web Application Firewall. Please login to the Cloudflare UI to review configuration. 
  - Cloudflare also throttles excessive requesters and offers an [I'm under attack](https://support.cloudflare.com/hc/en-us/articles/200170076-Understanding-Cloudflare-Under-Attack-mode-advanced-DDOS-protection-) mode should we ever need it.
  - A Cloudflare worker further improves security. [See its code](https://github.com/massgov/openmass/tree/develop/cloudflare).
- Drupal/PHP
  - The site does not have any end user accessible forms. This helps us avoid many possible atttack vectors and many security advisories happily have no relevance for this site.
  - The TFA and Password policy modules secure editor accounts from phishing and other attacks.
  - The Security Review module is enabled and provides a quick best practices checklist.  
  - User uploaded files are scanned for viruses via an AWS Lamda function.  
  - Our Behat tests check for XSS vulnerabilities. See [mass_xss module](https://github.com/massgov/openmass/tree/develop/docroot/modules/custom/mass_xss).
  - In CI we run `drush pm:security` and `drush pm:security-php` to catch outstanding security releases.  
  - [Drupal Security Team Handbook](https://www.drupal.org/docs/security-in-drupal/writing-secure-code-for-drupal)
- Misc
  - [OWASP Top 10](https://owasp.org/www-project-top-ten/)
  - We have configured Github's Dependabot to alert on insecure Javascript packages.  

