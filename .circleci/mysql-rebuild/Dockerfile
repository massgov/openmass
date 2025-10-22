# vim:set ft=dockerfile:
FROM mysql:8.0

# The official mysql:8.0 image declares VOLUME /var/lib/mysql, which prevents
# docker commit from capturing database changes. We work around this by
# changing the data directory to /var/lib/mysql-no-volume.
# See: https://github.com/docker-library/mysql/issues/44

# Copy the default data directory structure to the new location during build
RUN cp -a /var/lib/mysql/. /var/lib/mysql-no-volume/

EXPOSE 3306
CMD ["mysqld", "--datadir=/var/lib/mysql-no-volume"]
