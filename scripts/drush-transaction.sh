#!/usr/bin/env bash

if [[ $# -ne 2 ]]; then
    echo "Script requires two parameters" >&2
    echo "${0} AH_SITE_NAME SITE_DOMAIN" >&2
    exit 2
fi

# if there is a $HOME/.ssh/environment file,
# then read each line and export each variable from the file
SSH_ENVIRONMENT=$HOME/.ssh/environment
if [[ -f "${SSH_ENVIRONMENT}" ]]; then
  #export each of the variables from the file
  while read line; do export $line; done < ${SSH_ENVIRONMENT}
fi

logfile="/shared/logs/drush-cron.log"
exec > >(/usr/bin/tee -a "$logfile") 2>&1
if [ -n "${2}" ]; then
  uri="${2}"
else
  uri="${AH_SITE_NAME}.${AH_REALM}.acquia-sites.com"
fi

#echo "URI: ${uri}"
echo "***** Script ${0} Started: $(date --rfc-3339=seconds) *****"

echo "***** Running Drush status"
PHP_INI_SCAN_DIR=:$HOME/.drush drush  --root="/var/www/html/${AH_SITE_NAME}/docroot/"  --uri="${uri}" status
echo

echo "***** Running Drush cron"
PHP_INI_SCAN_DIR=:$HOME/.drush drush  --root="/var/www/html/${AH_SITE_NAME}/docroot/"  --uri="${uri}" cron
echo

echo "***** Running Drush entity usage tracking"
PHP_INI_SCAN_DIR=:$HOME/.drush drush  --root="/var/www/html/${AH_SITE_NAME}/docroot/"  --uri="${uri}" --time-limit=60 queue:run entity_usage_tracker

echo

echo -e "***** Script Completed: $(date --rfc-3339=seconds) *****\\n"
