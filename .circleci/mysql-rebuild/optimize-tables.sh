#!/bin/bash
# Rebuilds every InnoDB table in the database of the mysql service, so the
# committed image does not carry the free space left by import and sanitize.
# Run from .circleci/mysql-rebuild while the docker-compose services are up.
set -euo pipefail

mysql_exec() {
  docker-compose exec -T mysql mysql -u circle -pcircle circle "$@"
}

docker-compose exec -T mysql du -sh /var/lib/mysql-no-volume

# OPTIMIZE TABLE reports problems as result rows, not as SQL errors.
# The backticks in sed are literal SQL quoting.
# shellcheck disable=SC2016
mysql_exec -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND engine = 'InnoDB'" \
  | sed 's/.*/OPTIMIZE TABLE `&`;/' \
  | mysql_exec -N > /tmp/optimize.log
# Columns: table, operation, message type, message text.
if awk -F'\t' '$3 == "error" { print; failed = 1 } END { exit !failed }' /tmp/optimize.log; then
  exit 1
fi

docker-compose exec -T mysql du -sh /var/lib/mysql-no-volume
