#!/bin/bash
# Installs the DDEV version in $DDEV_VERSION and sets CI-friendly globals.
# The release archive is kept in the CircleCI cache (see the test job), so on a
# cache hit nothing is downloaded from GitHub.
set -euo pipefail

: "${DDEV_VERSION:?Set DDEV_VERSION}"
download_dir="${HOME}/ddev-download"

case "$(uname -m)" in
  x86_64) arch=amd64 ;;
  aarch64) arch=arm64 ;;
  *) echo "Unsupported architecture $(uname -m)." >&2; exit 1 ;;
esac

# From checksums.txt of the release. Add the new ones when DDEV_VERSION changes.
declare -A checksums=(
  [v1.25.0-amd64]=019a1ed5321e154978a09763d96adfd1c4b6b8cb62cdbdb616e2bd1ae30c46d8
  [v1.25.0-arm64]=136ae71e7da6db81ae0101b1259a6303ca9326051d593ba538a026bd682781d2
)
checksum="${checksums[${DDEV_VERSION}-${arch}]:-}"
if [[ -z "${checksum}" ]]; then
  echo "No checksum for DDEV ${DDEV_VERSION} on ${arch}, add it to $0." >&2
  exit 1
fi

name="ddev_linux-${arch}.${DDEV_VERSION}.tar.gz"
tarball="${download_dir}/${name}"
verify() { echo "${checksum}  ${tarball}" | sha256sum --check --quiet --status; }

if [[ -f "${tarball}" ]] && verify; then
  echo "Using ${name} from the cache."
else
  echo "Downloading ${name}."
  mkdir -p "${download_dir}"
  curl -fsSL -o "${tarball}" "https://github.com/ddev/ddev/releases/download/${DDEV_VERSION}/${name}"
  if ! verify; then
    echo "${name} does not match its checksum." >&2
    exit 1
  fi
fi

# What the official install_ddev.sh does after its download.
bin="$(mktemp -d)"
tar -xzf "${tarball}" -C "${bin}" ddev ddev-hostname mkcert
sudo install -m 0755 "${bin}/ddev" "${bin}/ddev-hostname" "${bin}/mkcert" /usr/local/bin/
rm -rf "${bin}"
mkcert -install

# The SSH agent is only needed for pulling from remote hosts.
ddev config global --instrumentation-opt-in=false --omit-containers=ddev-ssh-agent
ddev --version
