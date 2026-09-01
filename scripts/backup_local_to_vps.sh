#!/bin/bash
# Sauvegarde local entreprise → VPS
# Usage : bash scripts/backup_local_to_vps.sh [--dry-run] [--db-only] [--files-only]

set -euo pipefail
cd "$(dirname "$0")/.."

echo
echo "========================================"
echo " Fouta — Sauvegarde local → VPS"
echo " BDD + upload/ (dossier backup VPS)"
echo "========================================"
echo

exec php -d output_buffering=0 -d implicit_flush=1 scripts/backup_local_to_vps.php "$@"
