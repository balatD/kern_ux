#!/usr/bin/env bash
#
# Test runner for EXT:kern_ux.
#
# The extension supports two TYPO3 majors from one codebase, and their dependency
# graphs are mutually exclusive (Content Blocks 1.x is v13-only, 2.x is v14-only).
# So "run the tests" always means "pin a major, resolve, run" - never just phpunit.
#
# Runs inside DDEV when available, otherwise on the host.
set -euo pipefail

cd "$(dirname "$(readlink -f "$0")")/../.."
ROOT="$(pwd)"

TYPO3_MAJOR="14"
SUITE="all"
PHP_BIN="php"
COMPOSER_BIN="composer"
DB_DRIVER="pdo_sqlite"
RESTORE_COMPOSER_JSON=1

usage() {
    cat <<'USAGE'
Usage: Build/Scripts/runTests.sh [-t 13|14] [-s SUITE] [-d DRIVER] [-k]

  -t  TYPO3 major to test against (13 or 14). Default: 14
  -s  Suite: lint | cgl | cglFix | phpstan | unit | functional | all. Default: all
  -d  Functional test database driver. Default: pdo_sqlite
  -k  Keep the pinned composer.json instead of restoring it afterwards

Examples:
  Build/Scripts/runTests.sh -t 13 -s functional
  Build/Scripts/runTests.sh -t 14 -s all
USAGE
}

while getopts ":t:s:d:kh" opt; do
    case $opt in
        t) TYPO3_MAJOR="$OPTARG" ;;
        s) SUITE="$OPTARG" ;;
        d) DB_DRIVER="$OPTARG" ;;
        k) RESTORE_COMPOSER_JSON=0 ;;
        h) usage; exit 0 ;;
        *) usage; exit 1 ;;
    esac
done

case "$TYPO3_MAJOR" in
    13) CORE_CONSTRAINT="^13.4" ;;
    14) CORE_CONSTRAINT="^14.3" ;;
    *)  echo "Unsupported TYPO3 major: $TYPO3_MAJOR" >&2; exit 1 ;;
esac

cleanup() {
    if [ "$RESTORE_COMPOSER_JSON" = "1" ] && [ -f "$ROOT/composer.json.orig" ]; then
        mv "$ROOT/composer.json.orig" "$ROOT/composer.json"
    fi
}
trap cleanup EXIT

echo "==> Pinning TYPO3 $CORE_CONSTRAINT"
cp composer.json composer.json.orig
# Silences composer's root-version guess; the real version comes from the git tag.
export COMPOSER_ROOT_VERSION="${COMPOSER_ROOT_VERSION:-0.1.x-dev}"

# cms-form has to keep its --dev placement. Pinning it without the flag would
# promote it into `require` and quietly turn an optional dependency into a hard one.
$COMPOSER_BIN require --no-update -n "typo3/cms-core:$CORE_CONSTRAINT" >/dev/null
$COMPOSER_BIN require --no-update -n --dev "typo3/cms-form:$CORE_CONSTRAINT" "typo3/cms-rte-ckeditor:$CORE_CONSTRAINT" >/dev/null
# Two steps on purpose. typo3/class-alias-loader is itself a Composer plugin and
# differs between the majors (v1 on TYPO3 13, v2 on 14). Dumping the autoloader in
# the same process that upgraded the plugin runs the OLD plugin code against the NEW
# package and dies on a missing class, so the dump gets a fresh process.
$COMPOSER_BIN update -W --no-progress -n --no-autoloader
$COMPOSER_BIN dump-autoload --no-interaction

# A DI cache built against the other major puts TYPO3 into its failsafe container
# and every functional test then dies on a missing container entry.
rm -rf .Build/public/typo3temp var

run_lint()       { find Classes Configuration Tests Build ext_emconf.php ext_localconf.php -name '*.php' -print0 | xargs -0 -n1 -P4 $PHP_BIN -l > /dev/null; echo "lint OK"; }
run_cgl()        { $PHP_BIN vendor/bin/php-cs-fixer fix --dry-run --diff; }
run_cgl_fix()    { $PHP_BIN vendor/bin/php-cs-fixer fix; }
run_phpstan()    { $PHP_BIN vendor/bin/phpstan analyse --no-progress; }
run_unit()       { $PHP_BIN vendor/bin/phpunit -c Build/phpunit/UnitTests.xml; }
run_functional() { typo3DatabaseDriver="$DB_DRIVER" $PHP_BIN vendor/bin/phpunit -c Build/phpunit/FunctionalTests.xml; }

case "$SUITE" in
    lint)       run_lint ;;
    cgl)        run_cgl ;;
    cglFix)     run_cgl_fix ;;
    phpstan)    run_phpstan ;;
    unit)       run_unit ;;
    functional) run_functional ;;
    all)        run_lint; run_cgl; run_phpstan; run_unit; run_functional ;;
    *)          echo "Unknown suite: $SUITE" >&2; usage; exit 1 ;;
esac

echo "==> OK (TYPO3 $TYPO3_MAJOR, suite $SUITE)"
