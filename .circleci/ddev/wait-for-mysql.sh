#!/bin/bash
# Waits for the dbmass service to accept connections: 120 tries, 2 s apart.
set -euo pipefail

for _ in $(seq 1 120); do
  if ddev exec mysqladmin ping -h dbmass -u circle -pcircle --silent </dev/null; then
    exit 0
  fi
  sleep 2
done

echo "MySQL in dbmass did not come up." >&2
exit 1
