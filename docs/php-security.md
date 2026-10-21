# PHP security updates

CVE-2026-109618 and CVE-2026-109619 affect the PHP runtime serving HTTP
requests. Application changes and configuration mitigations do not replace a
runtime update. See the [PHP security advisory](https://www.php.net/archive/2026.php#2026-10-21-1).

This project requires PHP 8.3.36+, 8.4.27+, or 8.5.12+ within those release
series. Composer checks the executing PHP binary before installs and updates;
`config.platform.php` only selects the dependency resolution target. Locked
dependency versions are unchanged.

## Local and preview environments

The DDEV build upgrades PHP 8.4 CLI and FPM packages and fails if the patched
runtime is unavailable. Rebuild existing containers without cached layers:

```sh
ddev debug rebuild
ddev exec php scripts/check-php-security.php
ddev exec php-fpm8.4 -v
```

Tugboat checks the runtime during init, update, and build. Rebuild its base
preview from a freshly pulled, patched `tugboatqa/php:8.4-apache` image and then
rebuild derived previews. An old base preview will fail the check; the check
does not patch that image.

## Acquia and CI

Have Acquia update the web-serving PHP runtime in every environment to a fixed
release (or provide evidence of a vendor backport for both CVEs), restart all
PHP workers, and verify the web runtime after the restart. Acquia controls PHP
patch releases; changing this repository's platform override does not upgrade
the service. A CLI check alone does not verify Apache or FPM workers.

The CI web image comes from `massgov/Drupal-Container`. Its remediation branch
pins PHP 8.3.36. A separate approved image release and consumer tag update are
required before the existing `massgov/drupal-container:1.2.0-ci` image can pass
the new pre-Apache check. This change does not publish an image or change the
existing consumer to an unpublished tag.

Confirm each web-serving process reports a fixed version, run the normal
application and upload tests, and review monitoring before considering the
deployed environments remediated. Do not bypass the checks with `--no-scripts`
or `--ignore-platform-reqs`.
