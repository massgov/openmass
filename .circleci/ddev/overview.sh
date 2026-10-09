#!/bin/bash
# Prints the machine and the runtime the tests run on, for comparing builds.
set -euo pipefail

echo "CPUs: $(nproc)"
free -g
df -h /
docker images --format '{{.Repository}}:{{.Tag}} {{.Size}}'

ddev exec php -v
ddev exec mysql -h dbmass -u circle -pcircle circle -e 'SELECT VERSION()'
# Should list no volume for /var/lib/mysql-no-volume, see configure-ddev.sh.
docker inspect ddev-mass-dbmass --format '{{json .Mounts}}'
