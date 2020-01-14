#!/bin/bash
set -e

REPOSITORY_TYPE=github
CIRCLE_API="https://circleci.com/api"
PARAMETERS='"post-trigger": true'
PARAMETERS+=', "webhook": false'

if [ -z "$CIRCLE_PR_NUMBER" ]; then
  # This is an internal PR. Trigger additional testing.
  DATA="{ \"branch\": \"$CIRCLE_BRANCH\", \"parameters\": { $PARAMETERS } }"
  URL="${CIRCLE_API}/v2/project/${REPOSITORY_TYPE}/${CIRCLE_PROJECT_USERNAME}/${CIRCLE_PROJECT_REPONAME}/pipeline"
  echo "Triggering pipeline with data:"
  echo -e "URL:  $URL"
  echo -e "DATA:  $DATA"

  HTTP_RESPONSE=$(curl -s -u ${CIRCLE_PERSONAL_TOKEN}: -o response.txt -w "%{http_code}" -X POST --header "Content-Type: application/json" -d "$DATA" $URL)

  if [ "$HTTP_RESPONSE" -ge "200" ] && [ "$HTTP_RESPONSE" -lt "300" ]; then
      echo "API call succeeded."
      echo "Response:"
      cat response.txt
  else
      echo -e "\e[93mReceived status code: ${HTTP_RESPONSE}\e[0m"
      echo "Response:"
      cat response.txt
      exit 1
  fi
else
  echo -e "Skip trigger since this is a fork."
  exit 0;


