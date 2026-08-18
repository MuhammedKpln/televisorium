#!/usr/bin/env bash
#
# make-release.sh - build a release archive (.tar.gz)
#
# Usage:
#   ./make-release.sh [SOURCE_DIR] [OUTPUT_TARBALL]
#
# Defaults:
#   SOURCE_DIR   = /home/muhammed/Documents/televisorium
#   OUTPUT       = <script_dir>/dist/<app-id>-<version>.tar.gz
#
# All files are placed at the top level of the archive (no wrapping folder)
# and dev-only content is excluded (.git, node_modules, src, tests, lock files,
# build configs, ...).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="$(pwd)"
APP_ID="$(sed -n 's:.*<id>\(.*\)</id>.*:\1:p' "$SOURCE_DIR/appinfo/info.xml" | head -1)"
VERSION="$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "$SOURCE_DIR/appinfo/info.xml" | head -1)"

if [[ -z "$APP_ID" || -z "$VERSION" ]]; then
    echo "error: could not read <id>/<version> from $SOURCE_DIR/appinfo/info.xml" >&2
    exit 1
fi

OUTPUT="${2:-$SCRIPT_DIR/dist/$APP_ID-$VERSION.tar.gz}"

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

# App folders shipped in the release (copied to the archive root)
for dir in appinfo css img js lib templates; do
    if [[ -d "$SOURCE_DIR/$dir" ]]; then
        cp -r "$SOURCE_DIR/$dir" "$STAGE/"
    fi
done

# Top-level metadata files that should ship with the app
for file in CHANGELOG.md LICENSE README.md composer.json openapi.json; do
    if [[ -f "$SOURCE_DIR/$file" ]]; then
        cp "$SOURCE_DIR/$file" "$STAGE/"
    fi
done

if [[ -z "$(ls -A "$STAGE")" ]]; then
    echo "error: nothing to package in $SOURCE_DIR" >&2
    exit 1
fi

mkdir -p "$(dirname "$OUTPUT")"
tar -C "$STAGE" -czf "$OUTPUT" .

echo "wrote $OUTPUT"
echo "app id:      $APP_ID"
echo "app version: $VERSION"
echo "--- contents (first 15) ---"
tar -tzf "$OUTPUT" | head -15
