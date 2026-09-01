#!/bin/bash
# Installe le cron de sauvegarde local → VPS (tous les jours à 18h)
#
# Usage :
#   cd /var/www/fouta
#   bash scripts/install_backup_cron_entreprise.sh
#
# Variables :
#   BACKUP_CRON_HOUR=18 BACKUP_CRON_MIN=0 bash scripts/install_backup_cron_entreprise.sh

set -euo pipefail

WEB_ROOT="${FOUTA_WEB_ROOT:-/var/www/fouta}"
CRON_HOUR="${BACKUP_CRON_HOUR:-18}"
CRON_MIN="${BACKUP_CRON_MIN:-0}"
CRON_USER="${BACKUP_CRON_USER:-fouta}"
LOG_FILE="/var/log/fouta-backup-vps.log"
CRON_FILE="/etc/cron.d/fouta-backup-vps"

GREEN='\033[0;32m'
NC='\033[0m'
log() { echo -e "${GREEN}[backup-cron]${NC} $*"; }

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
    echo "Relancez avec sudo : sudo bash scripts/install_backup_cron_entreprise.sh"
    exit 1
fi

cd "$WEB_ROOT"

if [[ ! -f config/backup_local_to_vps.php ]]; then
    cp config/backup_local_to_vps.example.php config/backup_local_to_vps.php
    log "Créé config/backup_local_to_vps.php — éditez les identifiants BDD/SSH"
fi

chmod +x scripts/backup_local_to_vps.sh 2>/dev/null || true

touch "$LOG_FILE"
chown "$CRON_USER:$CRON_USER" "$LOG_FILE"

CRON_LINE="$CRON_MIN $CRON_HOUR * * * $CRON_USER /usr/bin/php $WEB_ROOT/scripts/backup_local_to_vps.php >> $LOG_FILE 2>&1"

cat > "$CRON_FILE" <<EOF
# Sauvegarde Fouta — serveur local entreprise → VPS (BDD + upload/)
# Généré par install_backup_cron_entreprise.sh
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

$CRON_LINE
EOF

chmod 644 "$CRON_FILE"
log "Cron installé : tous les jours à ${CRON_HOUR}:$(printf '%02d' "$CRON_MIN")"
log "Fichier : $CRON_FILE"
log "Log     : $LOG_FILE"
log ""
log "Test manuel : php $WEB_ROOT/scripts/backup_local_to_vps.php --dry-run"
log "Test réel   : php $WEB_ROOT/scripts/backup_local_to_vps.php"
