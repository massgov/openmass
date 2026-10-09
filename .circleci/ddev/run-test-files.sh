#!/bin/bash
# Runs the test files `circleci tests run` passes on stdin, one PHPUnit process
# per file, so a fatal error in one file does not stop the rest. Fails if any
# file failed.
set -uo pipefail

# The file names arrive separated by spaces or newlines.
mapfile -t files < <(tr ' ' '\n' | sed '/^$/d')

status=0
for file in "${files[@]}"; do
  # Name the report after the module path: two modules can have test files
  # with the same name, and one report would overwrite the other.
  rel="${file#*/modules/custom/}"
  junit="test-results/dtt/${rel//\//__}.xml"
  # ddev exec reads stdin, which would swallow the remaining file names.
  ddev exec phpunit "$file" --log-junit "/var/www/html/${junit}" </dev/null || status=1
  # PHPUnit records the file that defines each test method, which for
  # inherited tests is the base class. CircleCI splits by the file names it was
  # given, so record this one, or the next split has no timing for it.
  if [ -f "$junit" ]; then
    sed -i "s#file=\"[^\"]*\"#file=\"${file}\"#g" "$junit"
  fi
done
exit "$status"
