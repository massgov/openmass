#!/bin/bash
# Writes the CI-only DDEV files. Usage: configure-ddev.sh <database image>
# e.g. configure-ddev.sh massgov/mysql-sanitized:latest
set -euo pipefail

db_image="${1:?Pass the database image, e.g. massgov/mysql-sanitized:latest}"
: "${MYSQL_REBUILD_AWS_ACCOUNT_ID:?}"
: "${MYSQL_REBUILD_AWS_REGION:?}"

# Read by docker-compose.dbmass.yaml and the ecr-login pre-start hook.
# No MASS_DB_AWS_PROFILE_NAME: ecr-login then uses the role the job assumed.
cat > .ddev/.env <<ENV
MASS_DB_AWS_ACCOUNT_ID=${MYSQL_REBUILD_AWS_ACCOUNT_ID}
MASS_DB_AWS_REGION=${MYSQL_REBUILD_AWS_REGION}
MASS_DB_IMAGE=${db_image}
ENV

# Same Drupal settings as the old CI container (no development.services.yml).
cat > .ddev/config.ci.yaml <<'YAML'
web_environment:
  - DOCKER_ENV=ci
YAML

# Keep the database in the container layer. A named volume would first copy
# the whole data directory out of the image before MySQL starts.
cat > .ddev/docker-compose.ci.yaml <<'YAML'
services:
  dbmass:
    volumes: !reset []
YAML

# The web-build Dockerfile only adds developer CLIs (Tugboat, Jira, gh,
# Playwright libraries) that the tests do not use.
rm .ddev/web-build/Dockerfile

# Selenium is opt-in locally. `ddev service enable` no longer exists.
mv .ddev/.disabled-services/docker-compose.selenium-chrome.yaml .ddev/
