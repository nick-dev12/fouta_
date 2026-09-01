#!/bin/bash
# Mise à jour serveur entreprise — wrapper
# Usage : bash scripts/update_entreprise_server.sh [--dry-run] [--all-migrations] [--pull-prod] [--sync-push]

set -euo pipefail
cd "$(dirname "$0")/.."

if ! command -v php >/dev/null 2>&1; then
  echo "PHP introuvable." >&2
  exit 1
fi

echo
echo "========================================"
echo " Fouta — Mise à jour serveur entreprise"
echo " Git pull + migrations + vérif sync"
echo "========================================"
echo

exec php -d output_buffering=0 -d implicit_flush=1 scripts/update_entreprise_server.php "$@"
