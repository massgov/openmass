#!/bin/bash
# Runs the test files `circleci tests run` passes on stdin, one PHPUnit process
# per file, so a fatal error in one file does not stop the rest. Fails if any
# file failed, ran no tests or left no report.
set -uo pipefail

# The file names arrive separated by spaces or newlines.
mapfile -t files < <(tr ' ' '\n' | sed '/^$/d')
# A node can get nothing when CircleCI reruns only the failed tests.
# run-tests.sh checks that the whole suite was listed.
if (( ${#files[@]} == 0 )); then
  echo "No test files for this node."
  exit 0
fi

status=0
for file in "${files[@]}"; do
  # Name the report after the module path: two modules can have test files
  # with the same name, and one report would overwrite the other.
  rel="${file#*/modules/custom/}"
  junit="test-results/dtt/${rel//\//__}.xml"
  # stdin is already read into $files, but keep ddev exec off it anyway, so
  # the loop still works if it ever reads the list line by line.
  ddev exec phpunit "$file" --fail-on-empty-test-suite --log-junit "/var/www/html/${junit}" </dev/null || status=1
  if [ ! -f "$junit" ]; then
    echo "PHPUnit wrote no report for ${file}." >&2
    status=1
    continue
  fi
  # PHPUnit records the file that defines each test method, which for
  # inherited tests is the base class. CircleCI splits by the file names it was
  # given, so record this one, or the next split has no timing for it.
  sed -i "s#file=\"[^\"]*\"#file=\"${file}\"#g" "$junit" || status=1
done
exit "$status"
