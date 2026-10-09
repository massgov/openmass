#!/bin/bash
# Pulls the database image before `ddev start`, so the pull is timed as its own
# step. ddev start then finds the image locally.
set -euo pipefail

# shellcheck source=/dev/null
source .ddev/.env
.ddev/commands/host/ecr-login
time docker pull -q "${MASS_DB_AWS_ACCOUNT_ID}.dkr.ecr.${MASS_DB_AWS_REGION}.amazonaws.com/${MASS_DB_IMAGE}"
