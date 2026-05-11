#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DISTIGNORE_FILE="${PROJECT_ROOT}/.distignore"
DIST_DIR="${PROJECT_ROOT}/dist"

if [[ ! -f "${DISTIGNORE_FILE}" ]]; then
  echo "Fehler: .distignore nicht gefunden unter ${DISTIGNORE_FILE}" >&2
  exit 1
fi

MAIN_PLUGIN_FILE="$(grep -rl --include='*.php' 'Plugin Name:' "${PROJECT_ROOT}" | head -n 1 || true)"
if [[ -z "${MAIN_PLUGIN_FILE}" ]]; then
  echo "Fehler: Konnte keine Haupt-Plugin-Datei mit 'Plugin Name:' finden." >&2
  exit 1
fi

SLUG="$(basename "${PROJECT_ROOT}")"
VERSION="$(awk -F': ' '/^[[:space:]]*\* Version:/ {print $2; exit}' "${MAIN_PLUGIN_FILE}")"
VERSION="${VERSION%%[[:space:]]*}"

if [[ -z "${VERSION}" ]]; then
  echo "Fehler: Keine Plugin-Version in ${MAIN_PLUGIN_FILE} gefunden." >&2
  exit 1
fi

mkdir -p "${DIST_DIR}"

STAGE_DIR="$(mktemp -d)"
trap 'rm -rf "${STAGE_DIR}"' EXIT

rsync -a \
  --exclude-from="${DISTIGNORE_FILE}" \
  --exclude "dist" \
  "${PROJECT_ROOT}/" "${STAGE_DIR}/${SLUG}/"

ZIP_NAME="${SLUG}.${VERSION}.zip"
ZIP_PATH="${DIST_DIR}/${ZIP_NAME}"

(
  cd "${STAGE_DIR}"
  zip -rq "${ZIP_PATH}" "${SLUG}"
)

echo "Release-Archiv erstellt: ${ZIP_PATH}"
