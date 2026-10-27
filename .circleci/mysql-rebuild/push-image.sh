#!/bin/bash
# Pushes the committed database image with zstd-compressed layers.
# docker push always compresses with gzip. zstd layers were about 40% smaller
# for mysql-sanitized:latest and unpack faster on pull (needs Docker 23 or
# newer).
# Usage: push-image.sh <registry>/<repository>:<tag>
set -euo pipefail

image="${1:?Pass the image reference, e.g. <registry>/massgov/mysql-sanitized:latest}"
registry="${image%%/*}"

# Use the login from the "Log in to ECR" step, which is valid for 12 hours.
# Asking AWS for a new password here would need the role session from
# aws-cli/setup, which expires after an hour, and the super-sanitized builds
# reach this step close to that.
auth="${HOME}/.docker/config.json"
if ! grep -q "\"${registry}\"" "${auth}"; then
  echo "${auth} has no login for ${registry}." >&2
  exit 1
fi
skopeo() {
  docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
    -v "${auth}:/auth.json:ro" \
    quay.io/skopeo/stable:v1.16.1 "$@"
}

# docker-daemon: reads the image straight from the local Docker engine.
skopeo copy --dest-compress-format zstd --dest-authfile /auth.json \
  "docker-daemon:${image}" "docker://${image}"

# Fail if the registry did not get zstd layers.
manifest="$(skopeo inspect --raw --authfile /auth.json "docker://${image}")"
if ! grep -q 'tar+zstd' <<<"${manifest}"; then
  echo "Pushed ${image}, but its layers are not zstd compressed:" >&2
  echo "${manifest}" >&2
  exit 1
fi
