ARG BASE_IMAGE
FROM $BASE_IMAGE

# Install Tugboat CLI.
RUN curl -L https://dashboard.tugboatqa.com/cli/linux/tugboat.tar.gz > tugboat.tar.gz && \
  tar -zxf tugboat.tar.gz -C /usr/local/bin/ && rm -f tugboat.tar.gz

# Install Jira CLI.
RUN curl -L https://github.com/ankitpokhrel/jira-cli/releases/download/v1.5.2/jira_1.5.2_linux_arm64.tar.gz > jira.tar.gz && \
  tar -zxf jira.tar.gz -C /usr/local/bin/ && rm -f jira.tar.gz && mv /usr/local/bin/jira_1.5.2_linux_arm64/bin/jira /usr/local/bin/jira && rm -rf /usr/local/bin/jira_1.5.2_linux_arm64

# Install CircleCI CLI.
RUN curl -fLSs https://raw.githubusercontent.com/CircleCI-Public/circleci-cli/master/install.sh | bash

# Install GitHub CLI.
RUN curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg | sudo gpg --dearmor -o /usr/share/keyrings/githubcli-archive-keyring.gpg && \
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" | sudo tee /etc/apt/sources.list.d/github-cli.list > /dev/null && \
  sudo apt update && \
  sudo apt install gh

# Install New Relic CLI per https://github.com/newrelic/newrelic-cli#example-usage
RUN curl -L https://download.newrelic.com/install/newrelic-cli/scripts/install.sh | bash
