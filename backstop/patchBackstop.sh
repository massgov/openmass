#!/bin/bash

set -x
set -eo pipefail

# Run this before running Backstop.
# See https://github.com/garris/BackstopJS/issues/996
sed -i 's/^    await page.goto(translateUrl(url, logger));$/    await page.goto(translateUrl(url, logger), { waitUntil: "domcontentloaded" });/' /usr/local/lib/node_modules/backstopjs/core/util/runPuppet.js
cat /usr/local/lib/node_modules/backstopjs/core/util/runPuppet.js | grep "domcontentloaded"
