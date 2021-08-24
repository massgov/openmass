# Builds a MySQL image with local data directory. See 'command' in docker-compose.yml
# Approach borrowed from https://about.zoosk.com/en/engineering-blog/test-databases-docker-containers/.
# Later versions of Percona broke our approach so we pinned this.
FROM percona:5.6.40

RUN ["mkdir", "–p", "/var/lib/mysql-image"]

RUN echo "[mysqld]" > /etc/mysql/conf.d/mass-vm.cnf \
   && echo "datadir = /var/lib/mysql-image" >> /etc/mysql/conf.d/mass-vm.cnf

# use previous version of the entrypoint:
# https://github.com/docker-library/percona/commit/0f4b22adef232551c763308367d3b54d586a4a38
COPY docker-entrypoint.sh /usr/local/bin/
