# Builds a MySQL image with local data directory.
# Approach borrowed from https://medium.com/@stevenlandow/persist-share-dev-mysql-data-in-a-docker-image-with-commit-f9aa9910be0a
FROM mysql:5.7

RUN mkdir /var/lib/mysql-no-volume
CMD ["--datadir", "/var/lib/mysql-no-volume"]
