#!/bin/bash
# Production VPS → serveur local (tant que la prod reste la source principale)
# Import COMPLET : base de données + dossier upload/
#
# Usage :
#   bash scripts/sync_prod_to_local_entreprise.sh
#   bash scripts/sync_prod_to_local_entreprise.sh --db-only
#   bash scripts/sync_prod_to_local_entreprise.sh --files-only
#   bash scripts/sync_prod_to_local_entreprise.sh --dry-run
#
# Prérequis : config/pull_prod_entreprise.php

set -euo pipefail
cd "$(dirname "$0")/.."

echo
echo "========================================"
echo " Fouta — PRODUCTION → LOCAL ENTREPRISE"
echo " Import intégral (BDD + upload/)"
echo " À lancer tant que le site prod est"
echo " la source principale de vérité."
echo "========================================"
echo

exec php -d output_buffering=0 -d implicit_flush=1 scripts/pull_prod_to_entreprise.php "$@"
