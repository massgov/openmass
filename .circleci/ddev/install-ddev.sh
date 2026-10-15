#!/bin/bash
# Installs the DDEV version in $DDEV_VERSION and sets CI-friendly globals.
set -euo pipefail

: "${DDEV_VERSION:?Set DDEV_VERSION}"

curl -fsSL "https://raw.githubusercontent.com/ddev/ddev/${DDEV_VERSION}/scripts/install_ddev.sh" \
  | bash -s "${DDEV_VERSION}"

# The SSH agent is only needed for pulling from remote hosts.
ddev config global --instrumentation-opt-in=false --omit-containers=ddev-ssh-agent
ddev --version
