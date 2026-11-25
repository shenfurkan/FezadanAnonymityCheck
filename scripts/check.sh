#!/bin/sh
# Verify the module file set and PHP syntax, in this repo or in an installed target.
set -eu

usage() {
    echo "Usage: $0 [--target DIR]"
}

TARGET=""
while [ $# -gt 0 ]; do
    case "$1" in
        --target)
            if [ $# -lt 2 ]; then
                echo "Missing value for --target" >&2
                usage >&2
                exit 2
            fi
            TARGET=$2
            shift 2
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown argument: $1" >&2
            usage >&2
            exit 2
            ;;
    esac
done

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
REPO_DIR=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)

if [ -n "$TARGET" ]; then
    if [ ! -d "$TARGET" ]; then
        echo "Target is not a directory: $TARGET" >&2
        exit 1
    fi
    ROOT=$(CDPATH= cd -- "$TARGET" && pwd)
else
    ROOT="$REPO_DIR/module"
fi

FILES='app/Controllers/AnonymityCheckController.php
app/Controllers/AnonymityCheck/AnonymityGeoApiTrait.php
app/Controllers/AnonymityCheck/AnonymityIndexTrait.php
app/Controllers/AnonymityCheck/AnonymitySaveTrait.php
app/Views/front/anonymity_check.php
app/Views/front/anonymity/guide.php
app/Views/front/anonymity/script.php
app/Views/front/anonymity/scripts/context.php
app/Views/front/anonymity/scripts/helpers.php
app/Views/front/anonymity/scripts/map.php
app/Views/front/anonymity/scripts/modules/fingerprint.php
app/Views/front/anonymity/scripts/modules/network.php
app/Views/front/anonymity/scripts/modules/privacy.php
app/Views/front/anonymity/scripts/runner.php
app/Views/front/anonymity/seo.php
app/Views/front/anonymity/style.php
app/Views/front/anonymity/style/components.php
app/Views/front/anonymity/style/fonts.php
app/Views/front/anonymity/style/guide.php
app/Views/front/anonymity/style/layout.php
app/Views/front/anonymity/style/responsive.php
public_html/anonymitycheck/index.php
public_html/assets/img/anonymity-bg.jpg
public_html/assets/img/anonymity-bg-2.jpg
cron/update-tor-exits.php
tests/anonymity-geo-fallback.php
tests/e2e/anonymity-check.spec.ts
tests/e2e/anonymity-seo-media.spec.ts'

# The install script copies tests only with --with-tests.
OPTIONAL_FILES='tests/anonymity-geo-fallback.php
tests/e2e/anonymity-check.spec.ts
tests/e2e/anonymity-seo-media.spec.ts'

missing=0
optional=0
count=0
while IFS= read -r rel; do
    [ -n "$rel" ] || continue
    count=$((count + 1))
    if [ ! -f "$ROOT/$rel" ]; then
        is_optional=0
        for opt in $OPTIONAL_FILES; do
            if [ "$opt" = "$rel" ]; then
                is_optional=1
            fi
        done
        if [ "$is_optional" -eq 1 ] && [ -n "$TARGET" ]; then
            echo "OPTIONAL (not installed): $rel"
            optional=$((optional + 1))
        else
            echo "MISSING: $rel"
            missing=$((missing + 1))
        fi
    fi
done <<EOF
$FILES
EOF

if [ "$missing" -gt 0 ]; then
    echo "$missing of $count expected files are missing under $ROOT" >&2
    exit 1
fi
echo "All $((count - optional)) required module files are present under $ROOT"

if command -v php >/dev/null 2>&1; then
    checked=0
    failures=0
    while IFS= read -r rel; do
        [ -n "$rel" ] || continue
        [ -f "$ROOT/$rel" ] || continue
        case "$rel" in
            *.php)
                checked=$((checked + 1))
                if ! php -l "$ROOT/$rel" >/dev/null; then
                    echo "SYNTAX ERROR: $rel" >&2
                    failures=$((failures + 1))
                fi
                ;;
        esac
    done <<EOF
$FILES
EOF
    if [ "$failures" -gt 0 ]; then
        echo "$failures PHP file(s) failed php -l" >&2
        exit 1
    fi
    echo "php -l passed for $checked PHP files"
else
    echo "WARNING: php not found on PATH; skipped php -l" >&2
fi
