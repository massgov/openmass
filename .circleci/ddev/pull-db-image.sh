#!/bin/bash
# Pulls the database image before `ddev start`, so the pull is timed as its own
# step. ddev start then finds the image locally.
set -euo pipefail

# shellcheck source=/dev/null
source .ddev/.env
.ddev/commands/host/ecr-login
registry="${MASS_DB_AWS_ACCOUNT_ID}.dkr.ecr.${MASS_DB_AWS_REGION}.amazonaws.com"

SECONDS=0
# Keep the error output, drop the image name docker prints on success.
if ! error="$(docker pull -q "${registry}/${MASS_DB_IMAGE}" 2>&1 >/dev/null)"; then
  echo "${error}" >&2
  # :trimmed is built by the nightly mysql_rebuild workflow and could be
  # missing from the registry, for example after someone deleted the tag. Then
  # test against the full image. Any other error (network, auth) fails here, so
  # that the nodes of one run do not end up on different databases.
  if [[ "${MASS_DB_IMAGE}" != *:trimmed ]] || ! grep -qi 'manifest unknown' <<<"${error}"; then
    exit 1
  fi
  MASS_DB_IMAGE="${MASS_DB_IMAGE%:trimmed}:latest"
  echo "The trimmed image is not in the registry, using ${MASS_DB_IMAGE}." >&2
  sed -i "s#^MASS_DB_IMAGE=.*#MASS_DB_IMAGE=${MASS_DB_IMAGE}#" .ddev/.env
  docker pull -q "${registry}/${MASS_DB_IMAGE}"
fi
echo "Pulled ${MASS_DB_IMAGE} in ${SECONDS} s."

# A failed nightly build leaves the previous image under the tag, so the tests
# would keep passing on older and older data. Warn after two days, fail after a
# week.
created="$(docker inspect -f '{{.Created}}' "${registry}/${MASS_DB_IMAGE}")"
age_days=$(( ($(date +%s) - $(date -d "${created}" +%s)) / 86400 ))
echo "${MASS_DB_IMAGE} was built ${created}, ${age_days} days ago."
if (( age_days >= 7 )); then
  echo "${MASS_DB_IMAGE} is ${age_days} days old. Fix the mysql_rebuild workflow, then run it with the pipeline parameter trigger_workflow=mysql_rebuild; it pushes the shared tags from any branch." >&2
  exit 1
elif (( age_days >= 2 )); then
  echo "WARNING: ${MASS_DB_IMAGE} is ${age_days} days old. Check the mysql_rebuild workflow." >&2
fi
