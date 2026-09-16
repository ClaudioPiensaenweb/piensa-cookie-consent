#!/usr/bin/env bash
#
# Build the update manifest the agency build polls.
#
# The plugin's updater reads a JSON document describing the newest release: its
# version, where the ZIP is, and a checksum to verify what it downloaded. That
# document is static, so GitHub Pages can serve it and there is no update
# server to run.
#
# The package it points at is the agency ZIP, which carries the updater. The
# wp.org ZIP does not, and a client updating to that one would silently stop
# receiving updates.
#
# Signing: the updater verifies an RSA signature over "version|package|checksum"
# when one is present and the site requires it. If UPDATE_SIGNING_KEY holds a
# private key, this signs; otherwise the manifest ships unsigned and sites must
# turn off "Require a valid signature". The checksum is always present either
# way, so a corrupted or swapped download is still rejected.
#
# Usage:
#   scripts/build-update-manifest.sh --zip=PATH --version=X.Y.Z --repo=owner/name
#                                    [--output=PATH]

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ZIP=""
VERSION=""
REPO=""
OUTPUT="${ROOT}/build/update.json"

for arg in "$@"; do
    case "$arg" in
        --zip=*)     ZIP="${arg#*=}" ;;
        --version=*) VERSION="${arg#*=}" ;;
        --repo=*)    REPO="${arg#*=}" ;;
        --output=*)  OUTPUT="${arg#*=}" ;;
        *) echo "Unknown argument: $arg" >&2; exit 1 ;;
    esac
done

if [ -z "$ZIP" ] || [ -z "$VERSION" ] || [ -z "$REPO" ]; then
    echo "Usage: build-update-manifest.sh --zip=PATH --version=X.Y.Z --repo=owner/name" >&2
    exit 1
fi

if [ ! -f "$ZIP" ]; then
    echo "Package not found: $ZIP" >&2
    exit 1
fi

CHECKSUM="$(sha256sum "$ZIP" | cut -d' ' -f1)"
PACKAGE="https://github.com/${REPO}/releases/download/v${VERSION}/$(basename "$ZIP")"
DETAILS="https://github.com/${REPO}/releases/tag/v${VERSION}"

# Read the support headers from the readme rather than repeating them here.
REQUIRES="$(awk '/^Requires at least:/ {print $4; exit}' "${ROOT}/readme.txt")"
TESTED="$(awk '/^Tested up to:/ {print $4; exit}' "${ROOT}/readme.txt")"
REQUIRES_PHP="$(awk '/^Requires PHP:/ {print $3; exit}' "${ROOT}/readme.txt")"

SIGNATURE=""
if [ -n "${UPDATE_SIGNING_KEY:-}" ]; then
    KEY_FILE="$(mktemp)"
    trap 'rm -f "$KEY_FILE"' EXIT
    printf '%s' "$UPDATE_SIGNING_KEY" > "$KEY_FILE"

    SIGNATURE="$(
        printf '%s|%s|%s' "$VERSION" "$PACKAGE" "$CHECKSUM" \
            | openssl dgst -sha256 -sign "$KEY_FILE" \
            | openssl base64 -A
    )"
    echo "Manifest signed."
else
    echo "UPDATE_SIGNING_KEY is not set: the manifest will be unsigned." >&2
fi

mkdir -p "$(dirname "$OUTPUT")"

# Written with a heredoc rather than a JSON library: every value here is one
# this script produced — a version string, a URL it built, a hex digest and
# base64 — so there is nothing to escape and nothing to depend on.
cat > "$OUTPUT" <<JSON
{
  "version": "${VERSION}",
  "package": "${PACKAGE}",
  "details_url": "${DETAILS}",
  "checksum": "${CHECKSUM}",
  "checksum_alg": "sha256",
  "signature": "${SIGNATURE}",
  "signature_alg": "sha256",
  "requires": "${REQUIRES}",
  "tested": "${TESTED}",
  "requires_php": "${REQUIRES_PHP}"
}
JSON

echo "Wrote ${OUTPUT}"
cat "$OUTPUT"
