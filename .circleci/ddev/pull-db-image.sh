#!/bin/bash
# Pulls the database image before `ddev start`, so the pull is timed as its own
# step. ddev start then finds the image locally.
set -euo pipefail

# shellcheck source=/dev/null
source .ddev/.env
.ddev/commands/host/ecr-login
registry="${MASS_DB_AWS_ACCOUNT_ID}.dkr.ecr.${MASS_DB_AWS_REGION}.amazonaws.com"

SECONDS=0
docker pull -q "${registry}/${MASS_DB_IMAGE}" >/dev/null
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
