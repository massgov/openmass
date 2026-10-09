#!/bin/bash
# Splits the PHPUnit test files across the parallel nodes by past timings and
# runs this node's share.
set -euo pipefail

ddev exec mkdir -p test-results/dtt

ddev exec phpunit -c /var/www/html/phpunit.xml.dist --list-test-files --colors=never \
  | sed -n 's/^ - //p' \
  | circleci tests run --command=.circleci/ddev/run-test-files.sh --verbose --split-by=timings
