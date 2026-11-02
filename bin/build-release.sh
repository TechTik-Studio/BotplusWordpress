#!/usr/bin/env bash
set -e

# Build release zip for WordPress.org directory submission
PLUGIN_SLUG="botplus-ai-agents"
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="${PLUGIN_DIR}/dist"
STAGE_DIR="${DIST_DIR}/${PLUGIN_SLUG}"

echo "==> Preparing release package for ${PLUGIN_SLUG}..."
mkdir -p "${DIST_DIR}"
rm -rf "${STAGE_DIR}" "${DIST_DIR}/${PLUGIN_SLUG}.zip"
mkdir -p "${STAGE_DIR}"

echo "==> Syncing plugin files according to .distignore..."
rsync -av --exclude-from="${PLUGIN_DIR}/.distignore" "${PLUGIN_DIR}/" "${STAGE_DIR}/"

echo "==> Removing any hidden/system files from staged release..."
find "${STAGE_DIR}" -name ".DS_Store" -delete 2>/dev/null || true
find "${STAGE_DIR}" -name "._*" -delete 2>/dev/null || true
find "${STAGE_DIR}" -name "__MACOSX" -exec rm -rf {} + 2>/dev/null || true
find "${STAGE_DIR}" -name ".*" -not -name "." -delete 2>/dev/null || true

echo "==> Creating release archive ${DIST_DIR}/${PLUGIN_SLUG}.zip..."
cd "${DIST_DIR}"
zip -r -q -X "${PLUGIN_SLUG}.zip" "${PLUGIN_SLUG}"
rm -rf "${STAGE_DIR}"

ZIP_SIZE=$(du -h "${DIST_DIR}/${PLUGIN_SLUG}.zip" | cut -f1)
echo "==> Build complete: ${DIST_DIR}/${PLUGIN_SLUG}.zip (${ZIP_SIZE})"

