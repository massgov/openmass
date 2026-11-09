#!/bin/bash
# Turns InnoDB redo logging off for the import, sanitize and revision trim, and
# back on before the image is committed. The redo log only matters for crash
# recovery, and a failed build is thrown away anyway. Needs root, whose random
# password the mysql entrypoint prints to the container log.
# Usage: redo-log.sh disable|enable
set -euo pipefail

action="${1:?Pass disable or enable}"
case "${action}" in
  disable) expected=OFF ;;
  enable) expected=ON ;;
  *) echo "Pass disable or enable, not ${action}." >&2; exit 1 ;;
esac

# The first start initializes the data directory on a temporary server
# (port 0), then starts the real one on 3306. The X Plugin also reports "ready
# for connections" on 33060, hence the "mysqld:" and the trailing space. grep
# without -q reads the whole log, so docker-compose does not die of SIGPIPE and
# fail the pipeline.
ready=
for _ in $(seq 90); do
  if docker-compose logs --no-color mysql | grep 'mysqld: ready for connections.*port: 3306 ' >/dev/null; then
    ready=1
    break
  fi
  sleep 2
done
if [[ -z "${ready}" ]]; then
  echo "MySQL did not start within 3 minutes." >&2
  exit 1
fi

password="$(docker-compose logs --no-color mysql | grep -o 'GENERATED ROOT PASSWORD: [^ ]*' | tail -n 1 | cut -d ' ' -f 4 || true)"
if [[ -z "${password}" ]]; then
  echo "The mysql log has no generated root password." >&2
  exit 1
fi

mysql_root() {
  docker-compose exec -T -e MYSQL_PWD="${password}" mysql mysql -uroot "$@"
}

mysql_root -e "ALTER INSTANCE ${action^^} INNODB REDO_LOG"
state="$(mysql_root -N -B -e "SHOW GLOBAL STATUS LIKE 'Innodb_redo_log_enabled'" | cut -f 2)"
echo "Innodb_redo_log_enabled: ${state}"
if [[ "${state}" != "${expected}" ]]; then
  echo "Expected the redo log to be ${expected}." >&2
  exit 1
fi
