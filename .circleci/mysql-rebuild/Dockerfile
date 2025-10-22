# vim:set ft=dockerfile:
FROM mysql:8.0

# Remove the VOLUME declaration from the parent image to allow docker commit to work.
# See: https://github.com/docker-library/mysql/issues/44
# The official mysql:8.0 image declares VOLUME /var/lib/mysql, which prevents
# docker commit from capturing database changes. We need to remove this to
# create a committed image with the sanitized database.
#
# This is done by creating a new image that copies everything except the volume.
# We use a multi-stage build to accomplish this.

FROM mysql:8.0 as source

FROM ubuntu:22.04

# Copy MySQL installation from the official image
COPY --from=source / /

# Ensure the VOLUME is not declared in this derived image
# (it was inherited from the parent but we're starting fresh)

EXPOSE 3306
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["mysqld"]
