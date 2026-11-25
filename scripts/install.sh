#!/bin/sh
# Copy the AnonymityCheck module into an existing Fezadan checkout.
set -eu

usage() {
    echo "Usage: $0 --target /path/to/Fezadan [--with-tests]"
}

TARGET=""
WITH_TESTS=0
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
        --with-tests)
            WITH_TESTS=1
            shift
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

if [ -z "$TARGET" ]; then
    usage >&2
    exit 2
fi

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
MODULE_DIR="$SCRIPT_DIR/../module"

if [ ! -f "$TARGET/app/Core/App.php" ]; then
    echo "Refusing to install: $TARGET does not look like a Fezadan checkout (missing app/Core/App.php)." >&2
    exit 1
fi

for dir in app public_html cron; do
    mkdir -p "$TARGET/$dir"
    cp -R "$MODULE_DIR/$dir/." "$TARGET/$dir/"
done

if [ "$WITH_TESTS" -eq 1 ]; then
    mkdir -p "$TARGET/tests"
    cp -R "$MODULE_DIR/tests/." "$TARGET/tests/"
fi

echo "Module files copied to $TARGET."
echo "Host integration is still required: routing, config, CSP, .htaccess, footer link and sitemap."
echo "Follow host/README.md in this repository before testing the page."
