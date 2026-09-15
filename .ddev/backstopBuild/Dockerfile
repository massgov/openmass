ARG BASE_IMAGE
FROM $BASE_IMAGE

# Add custom entrypoint
COPY ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# delete the default 'node' user with uid 1000 and add current ddev user
ARG username
ARG uid
ARG gid
RUN userdel -r node
RUN (groupadd --gid $gid "$username" || groupadd "$username" || true) && (useradd  -l -m -s "/bin/bash" --gid "$username" --comment '' --uid $uid "$username" || useradd  -l -m -s "/bin/bash" --gid "$username" --comment '' "$username" || useradd  -l -m -s "/bin/bash" --gid "$gid" --comment '' "$username" || useradd -l -m -s "/bin/bash" --comment '' $username )

# Bullseye is archived. The base image's security and updates suites are no
# longer available, but the archived main suite contains the packages needed
# by this wrapper image.
RUN sed -i 's#deb.debian.org/debian#archive.debian.org/debian#g; /bullseye-security/d; /bullseye-updates/d' /etc/apt/sources.list

# Ensure the sudoers directory exists before the echo command runs
RUN mkdir -p /etc/sudoers.d

# Add sudo and sudoers in manner similar to other ddev containers.
RUN apt-get -o Acquire::Check-Valid-Until=false update && \
    apt-get install -y --no-install-recommends sudo && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* && \
    echo "ALL ALL=NOPASSWD: ALL" > /etc/sudoers.d/ddev-backstop && \
    chmod 440 /etc/sudoers.d/ddev-backstop

RUN npm install -g minimist

# Install dependencies for the Playwright version bundled with BackstopJS.
RUN /usr/local/lib/node_modules/backstopjs/node_modules/.bin/playwright install-deps chromium && \
    npm cache clean --force && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*
