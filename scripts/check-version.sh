#!/usr/bin/env bash
#
# Assert that every place recording the version agrees before a release.
# A mismatch makes wp.org serve a version that does not match the code.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MAIN="${ROOT}/piensa-cookie-consent.php"
README="${ROOT}/readme.txt"

header_version="$(awk '/^ \* Version:/ {print $3; exit}' "$MAIN")"
constant_version="$(grep -oP "PIENSA_COOKIE_CONSENT_VERSION',\s*'\K[^']+" "$MAIN")"
stable_tag="$(awk '/^Stable tag:/ {print $3; exit}' "$README")"

echo "Plugin header:  ${header_version}"
echo "PHP constant:   ${constant_version}"
echo "Readme stable:  ${stable_tag}"

status=0

if [ "$header_version" != "$constant_version" ]; then
    echo "ERROR: the plugin header and PIENSA_COOKIE_CONSENT_VERSION disagree." >&2
    status=1
fi

if [ "$header_version" != "$stable_tag" ]; then
    echo "ERROR: the plugin header and the readme stable tag disagree." >&2
    status=1
fi

# On a tag build the git tag must match too.
if [ -n "${GITHUB_REF_NAME:-}" ] && [ "${GITHUB_REF_TYPE:-}" = "tag" ]; then
    tag="${GITHUB_REF_NAME#v}"
    echo "Git tag:        ${tag}"
    if [ "$tag" != "$header_version" ]; then
        echo "ERROR: the git tag and the plugin version disagree." >&2
        status=1
    fi
fi

# The changelog must document the version being released.
if ! grep -q "^= ${header_version} =" "$README"; then
    echo "ERROR: readme.txt has no changelog entry for ${header_version}." >&2
    status=1
fi

[ "$status" -eq 0 ] && echo "Versions are consistent."
exit "$status"
