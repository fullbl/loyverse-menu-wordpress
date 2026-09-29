#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="fullbl-menu-sync-for-loyverse"
DIST_DIR="${ROOT}/dist"
STAGE="${DIST_DIR}/${SLUG}"
ZIP="${DIST_DIR}/${SLUG}.zip"

cd "$ROOT"

rm -rf "$STAGE" "$ZIP"
mkdir -p "$STAGE"

if command -v rsync >/dev/null 2>&1; then
	rsync -a --exclude-from="${ROOT}/.distignore" "${ROOT}/" "${STAGE}/"
else
	echo "rsync is required to build the release zip." >&2
	exit 1
fi

mkdir -p "$DIST_DIR"
(
	cd "$DIST_DIR"
	zip -r "${SLUG}.zip" "$SLUG" -x "*.DS_Store"
)

echo "Built ${ZIP}"
