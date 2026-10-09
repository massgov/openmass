#!/bin/bash
# Turns InnoDB redo logging off for the import and sanitize, and back on before
# the image is committed. The redo log only matters for crash recovery, and a
# failed build is thrown away anyway. Needs root, whose random password the
# mysql entrypoint prints to the container log.
# Usage: redo-log.sh disable|enable
set -euo pipefail

action="${1:?Pass disable or enable}"
password="$(docker-compose logs --no-color mysql | grep -o 'GENERATED ROOT PASSWORD: [^ ]*' | tail -n 1 | cut -d ' ' -f 4)"
if [[ -z "${password}" ]]; then
  echo "The mysql log has no generated root password." >&2
  exit 1
fi

mysql_root() {
  docker-compose exec -T -e MYSQL_PWD="${password}" mysql mysql -uroot "$@"
}

# The first start initializes the data directory on a temporary server
# (port 0), then starts the real one on 3306.
for _ in $(seq 90); do
  docker-compose logs --no-color mysql | grep -q 'ready for connections.*port: 3306' && break
  sleep 2
done

mysql_root -e "ALTER INSTANCE ${action^^} INNODB REDO_LOG"
mysql_root -e "SHOW GLOBAL STATUS LIKE 'Innodb_redo_log_enabled'"
