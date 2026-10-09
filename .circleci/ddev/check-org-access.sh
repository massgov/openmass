#!/bin/bash
# Shows that organization-based editing permissions are enforced both for
# drush and for requests served by Apache, as on production.
set -euo pipefail

# Single quotes: the variable is expanded inside the web container.
# shellcheck disable=SC2016
ddev exec 'echo "MASS_ORG_ACCESS_ENFORCE in shell: ${MASS_ORG_ACCESS_ENFORCE:-<unset>}"'
ddev drush php:eval 'echo "CLI isEnforcementEnabled: " . var_export(\Drupal::service("mass_org_access.settings")->isEnforcementEnabled(), TRUE) . "\n";'

check=docroot/org-access-env-check.php
trap 'rm -f "$check"' EXIT
cat > "$check" <<'PHP'
<?php
echo "web getenv: " . var_export(getenv("MASS_ORG_ACCESS_ENFORCE"), TRUE) . "\n";
PHP
ddev exec curl -sS http://web/org-access-env-check.php
