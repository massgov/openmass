#!/bin/bash
# Starts DDEV and prefixes every output line with the time, to see where the
# start spends its time.
set -euo pipefail

ddev start -y 2>&1 | while IFS= read -r line; do
  printf '%s %s\n' "$(date +%T)" "${line}"
done
