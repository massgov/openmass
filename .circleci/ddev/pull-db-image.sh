#!/bin/bash
# Pulls the database image before `ddev start`, so the pull is timed as its own
# step. ddev start then finds the image locally.
set -euo pipefail

# shellcheck source=/dev/null
source .ddev/.env
.ddev/commands/host/ecr-login
registry="${MASS_DB_AWS_ACCOUNT_ID}.dkr.ecr.${MASS_DB_AWS_REGION}.amazonaws.com"
if ! time docker pull -q "${registry}/${MASS_DB_IMAGE}"; then
  # The trimmed image is built by the nightly mysql_rebuild workflow. Until its
  # first run, and if a run fails to push it, test against the full image.
  [[ "${MASS_DB_IMAGE}" == *:trimmed ]] || exit 1
  fallback="${MASS_DB_IMAGE%:trimmed}:latest"
  echo "${MASS_DB_IMAGE} is not available, using ${fallback}." >&2
  sed -i "s#^MASS_DB_IMAGE=.*#MASS_DB_IMAGE=${fallback}#" .ddev/.env
  time docker pull -q "${registry}/${fallback}"
fi
