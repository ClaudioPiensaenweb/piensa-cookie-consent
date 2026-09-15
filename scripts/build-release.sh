#!/usr/bin/env bash
#
# Build the distributable ZIP for the WordPress.org plugin directory.
#
# The wp.org package must be production-ready: no dev tooling, no VCS
# metadata, and no self-hosted updater (guideline #8). Exclusions live in
# .distignore and are applied here.
#
# Usage:
#   scripts/build-release.sh [--version=X.Y.Z] [--output-dir=DIR] [--keep-updater]

set -euo pipefail

SLUG="piensa-cookie-consent"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUTPUT_DIR="${ROOT}/build"
VERSION=""
KEEP_UPDATER=0

for arg in "$@"; do
    case "$arg" in
        --version=*)    VERSION="${arg#*=}" ;;
        --output-dir=*) OUTPUT_DIR="${arg#*=}" ;;
        --keep-updater) KEEP_UPDATER=1 ;;
        *) echo "Unknown argument: $arg" >&2; exit 1 ;;
    esac
done

# Fall back to the readme stable tag so CI and local builds agree.
if [ -z "$VERSION" ]; then
    VERSION="$(awk '/^Stable tag:/ {print $3; exit}' "${ROOT}/readme.txt")"
fi

if [ -z "$VERSION" ]; then
    echo "Could not determine the version. Pass --version=X.Y.Z." >&2
    exit 1
fi

STAGING="$(mktemp -d)"
trap 'rm -rf "$STAGING"' EXIT

DEST="${STAGING}/${SLUG}"
mkdir -p "$DEST"

# rsync honours .distignore so the exclude list has a single source of truth.
EXCLUDE_FILE="${ROOT}/.distignore"
RSYNC_ARGS=(-a --delete)
while IFS= read -r line; do
    # Skip blank lines and comments.
    [ -z "$line" ] && continue
    case "$line" in \#*) continue ;; esac
    RSYNC_ARGS+=(--exclude="$line")
done < "$EXCLUDE_FILE"

# The agency build keeps the updater; the wp.org build never does.
if [ "$KEEP_UPDATER" -eq 1 ]; then
    RSYNC_ARGS=("${RSYNC_ARGS[@]/--exclude=includes\/class-updater.php/}")
fi

rsync "${RSYNC_ARGS[@]}" "${ROOT}/" "${DEST}/"

# Compile translations if the toolchain is available; the .mo files are build
# artefacts and are deliberately absent from version control.
if command -v msgfmt >/dev/null 2>&1; then
    for po in "${DEST}"/languages/*.po; do
        [ -e "$po" ] || continue
        msgfmt "$po" -o "${po%.po}.mo"
    done
fi

mkdir -p "$OUTPUT_DIR"
ZIP="${OUTPUT_DIR}/${SLUG}-${VERSION}.zip"
rm -f "$ZIP"

( cd "$STAGING" && zip -rq "$ZIP" "$SLUG" -x '*.DS_Store' )

SIZE_BYTES=$(wc -c < "$ZIP")
SIZE_MB=$(( SIZE_BYTES / 1048576 ))

# wp.org rejects submissions over 10 MB.
if [ "$SIZE_BYTES" -gt 10485760 ]; then
    echo "ERROR: the package is ${SIZE_MB} MB, over the 10 MB wp.org limit." >&2
    exit 1
fi

echo "Built ${ZIP} (${SIZE_BYTES} bytes)"
