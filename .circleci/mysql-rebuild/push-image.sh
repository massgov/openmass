#!/bin/bash
# Pushes the committed database image with zstd-compressed layers.
# docker push always compresses with gzip. zstd layers are about 40% smaller
# and unpack several times faster on pull (needs Docker 23 or newer).
# Usage: push-image.sh <registry>/<repository>:<tag>
set -euo pipefail

image="${1:?Pass the image reference, e.g. <registry>/massgov/mysql-sanitized:latest}"
: "${MYSQL_REBUILD_AWS_REGION:?}"

password="$(aws ecr get-login-password --region "${MYSQL_REBUILD_AWS_REGION}")"
skopeo() {
  docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
    quay.io/skopeo/stable:v1.16.1 "$@"
}

# docker-daemon: reads the image straight from the local Docker engine.
skopeo copy --dest-compress-format zstd --dest-creds "AWS:${password}" \
  "docker-daemon:${image}" "docker://${image}"

# Fail if the registry did not get zstd layers.
manifest="$(skopeo inspect --raw --creds "AWS:${password}" "docker://${image}")"
if ! grep -q 'tar+zstd' <<<"${manifest}"; then
  echo "Pushed ${image}, but its layers are not zstd compressed:" >&2
  echo "${manifest}" >&2
  exit 1
fi
