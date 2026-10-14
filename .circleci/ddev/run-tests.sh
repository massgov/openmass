#!/bin/bash
# Splits the PHPUnit test files across the parallel nodes by past timings and
# runs this node's share.
set -euo pipefail

ddev exec mkdir -p test-results/dtt

list="$(mktemp)"
ddev exec phpunit -c /var/www/html/phpunit.xml.dist --list-test-files --colors=never \
  | sed -n 's/^ - //p' > "${list}"
# About 130 files today. Fewer means the list format changed and the sed above
# stopped matching, or test classes stopped loading, and would otherwise pass
# without running them.
count="$(wc -l < "${list}")"
echo "${count} test files in the suite."
if (( count < 120 )); then
  echo "Expected at least 120 test files, see phpunit --list-test-files." >&2
  exit 1
fi

circleci tests run --command=.circleci/ddev/run-test-files.sh --verbose --split-by=timings < "${list}"
