#!/usr/bin/env bash
#
# Build the distributable ZIP for the WordPress.org plugin directory.
#
# The wp.org package must be production-ready: no dev tooling, no VCS metadata,
# and no self-hosted updater (guideline #8). Exclusions live in .distignore and
# are applied here, so the list has a single source of truth.
#
# Usage:
#   scripts/build-release.sh [--version=X.Y.Z] [--output-dir=DIR] [--keep-updater]
#                            [--suffix=NAME]

set -euo pipefail

SLUG="piensa-cookie-consent"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUTPUT_DIR="${ROOT}/build"
VERSION=""
KEEP_UPDATER=0
SUFFIX=""

for arg in "$@"; do
    case "$arg" in
        --version=*)    VERSION="${arg#*=}" ;;
        --output-dir=*) OUTPUT_DIR="${arg#*=}" ;;
        --keep-updater) KEEP_UPDATER=1 ;;
        --suffix=*)     SUFFIX="-${arg#*=}" ;;
        *) echo "Unknown argument: $arg" >&2; exit 1 ;;
    esac
done

# Fall back to the readme stable tag, so CI and local builds agree.
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

# Copy everything, then remove the excluded paths. rsync would be tidier but is
# absent from Git Bash, and the release has to build on the developer's machine
# as well as in CI.
cp -R "${ROOT}/." "${DEST}/"
rm -rf "${DEST}/.git"

while IFS= read -r pattern || [ -n "$pattern" ]; do
    pattern="$(printf '%s' "$pattern" | tr -d '\r')"
    [ -z "$pattern" ] && continue
    case "$pattern" in \#*) continue ;; esac

    # The agency build keeps the updater; the wp.org build never does.
    if [ "$KEEP_UPDATER" -eq 1 ] && [ "$pattern" = "includes/class-updater.php" ]; then
        continue
    fi

    # Leading slashes anchor to the package root; the glob is relative either way.
    pattern="${pattern#/}"

    # shellcheck disable=SC2086 # The pattern is a glob and must stay unquoted.
    rm -rf ${DEST}/${pattern}
done < "${ROOT}/.distignore"

# Compile translations if the toolchain is available. The .mo files are build
# artefacts and are deliberately absent from version control.
if command -v msgfmt >/dev/null 2>&1; then
    for po in "${DEST}"/languages/*.po; do
        [ -e "$po" ] || continue
        msgfmt "$po" -o "${po%.po}.mo"
    done
fi

# Make the archive reproducible. Without this, two builds of identical code
# produce different bytes — the file modification times go into the ZIP — and
# so different checksums. The update manifest publishes a checksum of the
# package, so a rebuild would invalidate a manifest that is otherwise still
# correct, and the updater would refuse a download that is perfectly good.
SOURCE_EPOCH="${SOURCE_DATE_EPOCH:-}"
if [ -z "$SOURCE_EPOCH" ]; then
    SOURCE_EPOCH="$(git -C "$ROOT" log -1 --format=%ct 2>/dev/null || echo 1700000000)"
fi

find "$DEST" -exec touch -d "@${SOURCE_EPOCH}" {} + 2>/dev/null || true

mkdir -p "$OUTPUT_DIR"

# Resolve to an absolute path: the zip(1) branch below runs from the staging
# directory, where a relative output path would point somewhere else entirely.
OUTPUT_DIR="$(cd "$OUTPUT_DIR" && pwd)"
# The suffix keeps the two builds apart when both are published to the
# same release: a client updating with the wp.org ZIP would lose the
# updater and stop receiving updates altogether.
ZIP="${OUTPUT_DIR}/${SLUG}-${VERSION}${SUFFIX}.zip"
rm -f "$ZIP"

# zip(1) is absent from Git Bash, so fall back to Python's zipfile, which is
# available wherever the rest of the toolchain is.
if command -v zip >/dev/null 2>&1; then
    ( cd "$STAGING" && TZ=UTC zip -rqX "$ZIP" "$SLUG" -x '*.DS_Store' )
else
    SOURCE_EPOCH="$SOURCE_EPOCH" python - "$STAGING" "$SLUG" "$ZIP" <<'PYTHON'
import os
import sys
import zipfile

staging, slug, target = sys.argv[1:4]

import time

# A fixed timestamp for every entry, so the archive depends only on its
# contents. zipfile stores whatever mtime it finds otherwise.
epoch = int(os.environ.get("SOURCE_EPOCH", "1700000000"))
stamp = time.gmtime(epoch)[:6]

with zipfile.ZipFile(target, "w", zipfile.ZIP_DEFLATED) as archive:
    for root, dirs, files in os.walk(os.path.join(staging, slug)):
        dirs.sort()
        for name in sorted(files):
            if name == ".DS_Store":
                continue
            path = os.path.join(root, name)
            arcname = os.path.relpath(path, staging).replace(os.sep, "/")

            info = zipfile.ZipInfo(arcname, date_time=stamp)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o644 << 16

            with open(path, "rb") as handle:
                archive.writestr(info, handle.read())
PYTHON
fi

SIZE_BYTES=$(wc -c < "$ZIP")

# wp.org rejects submissions over 10 MB.
if [ "$SIZE_BYTES" -gt 10485760 ]; then
    echo "ERROR: the package is $(( SIZE_BYTES / 1048576 )) MB, over the 10 MB wp.org limit." >&2
    exit 1
fi

echo "Built ${ZIP} (${SIZE_BYTES} bytes)"
