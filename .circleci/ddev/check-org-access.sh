#!/bin/bash
# Fails unless organization-based editing permissions are enforced both for
# drush and for requests served by Apache, as on production.
# MASS_ORG_ACCESS_ENFORCE comes from .ddev/config.yaml and
# .ddev/docker-compose.org-access.yaml.
set -euo pipefail

# Single quotes: the variable is expanded inside the web container.
# shellcheck disable=SC2016
ddev exec 'echo "MASS_ORG_ACCESS_ENFORCE in shell: ${MASS_ORG_ACCESS_ENFORCE:-<unset>}"'
cli="$(ddev drush php:eval 'echo var_export(\Drupal::service("mass_org_access.settings")->isEnforcementEnabled(), TRUE);')"
echo "CLI isEnforcementEnabled: ${cli}"

# The env var part of OrgAccessSettings::isEnforcementEnabled(), without the
# State fallback, so this fails when Apache does not see the variable.
check=docroot/org-access-env-check.php
trap 'rm -f "$check"' EXIT
cat > "$check" <<'PHP'
<?php
$env = getenv('MASS_ORG_ACCESS_ENFORCE');
echo var_export(in_array(strtolower((string) $env), ['1', 'true', 'yes', 'on'], TRUE), TRUE);
PHP
web="$(ddev exec curl -fsS http://web/org-access-env-check.php)"
echo "Web MASS_ORG_ACCESS_ENFORCE enabled: ${web}"

if [[ "${cli}" != true || "${web}" != true ]]; then
  echo "Organization access is not enforced, see .ddev/config.yaml." >&2
  exit 1
fi
