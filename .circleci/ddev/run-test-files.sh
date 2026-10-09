#!/bin/bash
# Runs the test files `circleci tests run` passes on stdin, one PHPUnit process
# per file, so a fatal error in one file does not stop the rest. Fails if any
# file failed.
set -uo pipefail

# The file names arrive separated by spaces or newlines.
mapfile -t files < <(tr ' ' '\n' | sed '/^$/d')

status=0
for file in "${files[@]}"; do
  # ddev exec reads stdin, which would swallow the remaining file names.
  ddev exec phpunit "$file" --log-junit "/var/www/html/test-results/dtt/${file##*/}.xml" </dev/null || status=1
done
exit "$status"
