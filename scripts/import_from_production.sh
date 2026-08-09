#!/bin/bash
# Wrapper : import BDD + images depuis la production
# Usage : sudo ./scripts/import_from_production.sh [--db-only|--files-only|--find-upload-path]

set -euo pipefail
cd "$(dirname "$0")/.."
exec php scripts/import_from_production.php "$@"
