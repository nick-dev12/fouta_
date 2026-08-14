#!/bin/bash
# Production VPS → serveur local entreprise (BDD + upload/)
# Usage : ./scripts/pull_prod_to_entreprise.sh [--db-only|--files-only|--dry-run]

set -euo pipefail
cd "$(dirname "$0")/.."

if ! command -v php >/dev/null 2>&1; then
  echo "PHP introuvable dans le PATH." >&2
  exit 1
fi

echo
echo "========================================"
echo " Fouta - Production vers ENTREPRISE"
echo " BDD + dossier upload/ depuis le VPS"
echo "========================================"
echo

exec php -d output_buffering=0 -d implicit_flush=1 scripts/pull_prod_to_entreprise.php "$@"
