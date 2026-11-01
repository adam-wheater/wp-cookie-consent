#!/usr/bin/env bash
# Builds the installable zip. Run from anywhere.
set -euo pipefail
cd "$(dirname "$0")/.."
php tools/check.php
node tools/check.js
rm -f simple-cookie-consent.zip
zip -rq simple-cookie-consent.zip simple-cookie-consent -x '*.DS_Store'
echo "wrote $(pwd)/simple-cookie-consent.zip"
