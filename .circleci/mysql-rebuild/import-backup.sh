#!/bin/bash
# Imports the latest production backup with several mysql clients at once.
# scripts/ma-refresh-local --db-prep-only feeds the whole dump through one
# client, so the import used one CPU for about 25 minutes. Here the dump is cut
# into one file per table while it downloads, and finished files are loaded in
# parallel. Runs in the drupal container, then sanitizes like ma-refresh-local.
# Usage: import-backup.sh [workers]
set -euo pipefail

workers="${1:-4}"
dir="$(mktemp -d)"
started="$(date +%s)"
log() { echo "[$(($(date +%s) - started))s] $*"; }

sudo apk add --no-cache gawk findutils pigz >/dev/null

drush -y sql:create --extra=--disable-ssl-verify-server-cert
url="$(drush ma:latest-backup-url prod)"
MYSQL_CMD="$(drush sql:connect --extra=--disable-ssl-verify-server-cert)"
export MYSQL_CMD
log "Importing with ${workers} clients"

# Same tables as ma-refresh-local skips.
# gawk prints each finished table file, xargs loads it and deletes it. The
# lines before the first table (character set, FOREIGN_KEY_CHECKS=0 and so on)
# are repeated at the top of every file.
wget -q -O - "${url}" \
  | pigz -dc \
  | grep -v -e '^INSERT INTO `cache_' -e '^INSERT INTO `migrate_map_' -e '^INSERT INTO `config_log' -e '^INSERT INTO `key_value_expire' -e '^INSERT INTO `sessions' \
  | gawk -v dir="${dir}" '
      function finish() { if (out != "") { close(out); print out; fflush(); } }
      /^-- Table structure for table / {
        finish()
        out = sprintf("%s/%05d.sql", dir, ++n)
        printf "%s", header > out
      }
      { if (out == "") header = header $0 "\n"; else print > out }
      END {
        if (n == 0) { out = dir "/00000.sql"; printf "%s", header > out }
        finish()
      }' \
  | xargs -P "${workers}" -I {} sh -c '${MYSQL_CMD} < "$1" && rm "$1"' _ {}
log "Import finished"

rmdir "${dir}"
drush -y sql:sanitize
log "Sanitized"
