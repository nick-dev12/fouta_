#!/bin/bash
# =============================================================================
# Installation Fouta — serveur Ubuntu local (LAN)
# Usage :
#   chmod +x scripts/install_local_server.sh
#   sudo FOUTA_DB_PASS='MotDePasseSolide!' FOUTA_SYNC_TOKEN='VotreTokenSync' ./scripts/install_local_server.sh
#
# Prérequis : Ubuntu 22.04/24.04, accès sudo, Internet (git clone + composer)
# =============================================================================

set -euo pipefail

FOUTA_WEB_ROOT="${FOUTA_WEB_ROOT:-/var/www/fouta}"
FOUTA_DB_NAME="${FOUTA_DB_NAME:-fouta_local}"
FOUTA_DB_USER="${FOUTA_DB_USER:-fouta_user}"
FOUTA_DB_PASS="${FOUTA_DB_PASS:-}"
FOUTA_LAN_IP="${FOUTA_LAN_IP:-192.168.1.217}"
FOUTA_GIT_REPO="${FOUTA_GIT_REPO:-https://github.com/nick-dev12/fouta_.git}"
FOUTA_SYNC_TOKEN="${FOUTA_SYNC_TOKEN:-}"
FOUTA_NODE_ID="${FOUTA_NODE_ID:-local_entreprise}"
FOUTA_REMOTE_URL="${FOUTA_REMOTE_URL:-https://e.foutapoidslourds.com}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()  { echo -e "${GREEN}[fouta]${NC} $*"; }
warn() { echo -e "${YELLOW}[fouta]${NC} $*"; }
err()  { echo -e "${RED}[fouta]${NC} $*" >&2; }

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
    err "Relancez avec sudo : sudo $0"
    exit 1
fi

REAL_USER="${SUDO_USER:-$USER}"
if [[ "$REAL_USER" == "root" ]]; then
    REAL_USER="${FOUTA_SSH_USER:-jomas}"
fi

if [[ -z "$FOUTA_DB_PASS" ]]; then
    read -rsp "Mot de passe MySQL pour $FOUTA_DB_USER : " FOUTA_DB_PASS
    echo
fi
if [[ -z "$FOUTA_DB_PASS" ]]; then
    err "FOUTA_DB_PASS requis."
    exit 1
fi

log "=== 1/10 — Paquets LAMP ==="
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq \
    apache2 mysql-server \
    php php-cli libapache2-mod-php \
    php-mysql php-curl php-json php-mbstring php-xml php-gd php-zip php-intl \
    unzip git curl

if ! command -v composer >/dev/null 2>&1; then
    log "Installation Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

log "=== 2/10 — Base MySQL ==="
mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`${FOUTA_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${FOUTA_DB_USER}'@'localhost' IDENTIFIED BY '${FOUTA_DB_PASS}';
GRANT ALL PRIVILEGES ON \`${FOUTA_DB_NAME}\`.* TO '${FOUTA_DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

log "MySQL : autorisation triggers sync (log_bin_trust_function_creators)"
mysql -u root <<'SQL'
SET GLOBAL log_bin_trust_function_creators = 1;
SQL
if ! grep -q 'log_bin_trust_function_creators' /etc/mysql/mysql.conf.d/mysqld.cnf 2>/dev/null; then
    echo 'log_bin_trust_function_creators = 1' >> /etc/mysql/mysql.conf.d/mysqld.cnf
    systemctl restart mysql
fi

log "=== 3/10 — Code application ==="
mkdir -p "$(dirname "$FOUTA_WEB_ROOT")"
if [[ ! -d "$FOUTA_WEB_ROOT/.git" ]]; then
    if [[ -d "$FOUTA_WEB_ROOT" && "$(ls -A "$FOUTA_WEB_ROOT" 2>/dev/null)" ]]; then
        warn "$FOUTA_WEB_ROOT existe déjà — pas de git clone (mise à jour manuelle)."
    else
        rm -rf "$FOUTA_WEB_ROOT"
        git clone "$FOUTA_GIT_REPO" "$FOUTA_WEB_ROOT"
    fi
else
    log "Dépôt git existant — git pull..."
    sudo -u "$REAL_USER" git -C "$FOUTA_WEB_ROOT" pull origin main
fi

chown -R "$REAL_USER:www-data" "$FOUTA_WEB_ROOT"

log "=== 4/10 — Composer ==="
sudo -u "$REAL_USER" bash -c "cd '$FOUTA_WEB_ROOT' && composer install --no-dev --optimize-autoloader"

log "=== 5/10 — Fichiers de configuration ==="
cd "$FOUTA_WEB_ROOT"

if [[ ! -f conn/conn.php ]]; then
    cp conn/conn.example.php conn/conn.php
fi
if [[ ! -f config/site.php ]]; then
    cp config/site.example.php config/site.php
fi
if [[ ! -f config/sync.php ]]; then
    cp config/sync.example.php config/sync.php
fi

# conn.php
cat > conn/conn.php <<PHP
<?php
\$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists(\$autoload)) {
    require_once \$autoload;
}
\$db_host = 'localhost';
\$db_name = '${FOUTA_DB_NAME}';
\$db_user = '${FOUTA_DB_USER}';
\$db_pass = '${FOUTA_DB_PASS}';
\$pdo_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    \$db = new PDO(
        "mysql:host=\$db_host;dbname=\$db_name;charset=utf8mb4",
        \$db_user,
        \$db_pass,
        \$pdo_options
    );
    \$db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding('UTF-8');
    }
    ini_set('default_charset', 'UTF-8');
} catch (PDOException \$e) {
    \$db = null;
}
PHP

# site.php — URL LAN
cat > config/site.php <<PHP
<?php
return [
    'site_url' => 'http://${FOUTA_LAN_IP}',
];
PHP

# sync.php — push local → VPS
if [[ -n "$FOUTA_SYNC_TOKEN" ]]; then
    cat > config/sync.php <<PHP
<?php
return [
    'node_id' => '${FOUTA_NODE_ID}',
    'remote_url' => '${FOUTA_REMOTE_URL}',
    'remote_api_path' => '/sync/api.php',
    'remote_api_token' => '${FOUTA_SYNC_TOKEN}',
    'sync_direction' => 'push_only',
    'node_priority_on_tie' => false,
    'batch_limit' => 500,
    'sync_include_files' => true,
    'upload_dirs' => ['upload'],
    'upload_dirs_priority' => [
        'upload/produits', 'upload/categories', 'upload/slider', 'upload/logos',
        'upload/employes_photos', 'upload/videos',
    ],
    'files_batch_count' => 8,
    'http_timeout' => 300,
    'verify_ssl' => true,
];
PHP
else
    warn "FOUTA_SYNC_TOKEN non défini — éditez config/sync.php manuellement."
fi

log "=== 6/10 — .htaccess local (sans redirection HTTPS prod) ==="
if [[ -f .htaccess ]]; then
    cp .htaccess .htaccess.backup.prod
    # Désactiver redirections vers e.foutapoidslourds.com
    sed -i 's/^\(RewriteRule \^ https:\/\/e\.foutapoidslourds\.com\)/#\1/' .htaccess
    sed -i 's/^\(RewriteCond %{HTTPS} off\)/#\1/' .htaccess
    sed -i 's/^\(RewriteCond %{HTTP:X-Forwarded-Proto} !https\)/#\1/' .htaccess
    sed -i 's/^\(RewriteCond %{REQUEST_URI} !\^\\\/\\.well-known\/acme-challenge\/\)/#\1/' .htaccess
fi

log "=== 7/10 — Apache VirtualHost ==="
# S'assurer qu'un seul mod_php est actif pour Apache
if command -v a2query >/dev/null 2>&1; then
    ENABLED_PHP=$(a2query -m 2>/dev/null | grep -oE 'php[0-9.]+' | head -1 || true)
    CLI_PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
    if [[ -n "$ENABLED_PHP" && "$ENABLED_PHP" != "php${CLI_PHP_VER}" ]]; then
        warn "Apache utilise $ENABLED_PHP, CLI en PHP $CLI_PHP_VER — bascule vers php${CLI_PHP_VER}"
        a2dismod "$ENABLED_PHP" >/dev/null 2>&1 || true
        a2enmod "php${CLI_PHP_VER}" >/dev/null 2>&1 || true
    fi
fi
cp deploy/apache/fouta-local.conf /etc/apache2/sites-available/fouta.conf
sed -i "s/ServerName .*/ServerName ${FOUTA_LAN_IP}/" /etc/apache2/sites-available/fouta.conf

a2enmod rewrite >/dev/null
a2ensite fouta.conf >/dev/null 2>&1 || true
a2dissite 000-default.conf >/dev/null 2>&1 || true
systemctl reload apache2

log "=== 8/10 — Permissions upload ==="
mkdir -p upload
chown -R "$REAL_USER:www-data" "$FOUTA_WEB_ROOT"
chown -R www-data:www-data "$FOUTA_WEB_ROOT/upload"
find "$FOUTA_WEB_ROOT" -type d -exec chmod 755 {} \;
find "$FOUTA_WEB_ROOT" -type f -exec chmod 644 {} \;
chmod -R 775 "$FOUTA_WEB_ROOT/upload"

log "=== 9/10 — Import base de données ==="
if [[ -n "${FOUTA_SQL_DUMP:-}" && -f "$FOUTA_SQL_DUMP" ]]; then
    log "Import de $FOUTA_SQL_DUMP ..."
    mysql -u "$FOUTA_DB_USER" -p"$FOUTA_DB_PASS" "$FOUTA_DB_NAME" < "$FOUTA_SQL_DUMP"
elif [[ -f migrations/schema_production_install_complet.sql ]]; then
    warn "Aucun dump fourni (FOUTA_SQL_DUMP=...)."
    warn "Import schéma vierge ou importez un dump manuellement :"
    echo "  mysql -u $FOUTA_DB_USER -p $FOUTA_DB_NAME < votre_dump.sql"
    read -rp "Importer le schéma vierge maintenant ? [o/N] " REP
    if [[ "${REP,,}" == "o" ]]; then
        mysql -u "$FOUTA_DB_USER" -p"$FOUTA_DB_PASS" "$FOUTA_DB_NAME" < migrations/schema_production_install_complet.sql
        sudo -u www-data php migrations/run_migration_production_ajouts.php || true
    fi
fi

log "=== 10/10 — Sync + cron ==="
if [[ -f migrations/run_add_sync_columns.php ]]; then
    sudo -u www-data php migrations/run_add_sync_columns.php || warn "run_add_sync_columns : vérifiez manuellement"
    sudo -u www-data php migrations/run_assign_sync_uuids.php || warn "run_assign_sync_uuids : vérifiez manuellement"
fi

CRON_LINE="*/30 * * * * www-data /usr/bin/php ${FOUTA_WEB_ROOT}/scripts/sync_local_to_vps.php >> /var/log/fouta-sync.log 2>&1"
if ! grep -qF "sync_local_to_vps.php" /etc/crontab 2>/dev/null; then
    echo "$CRON_LINE" >> /etc/crontab
    log "Cron sync ajouté (toutes les 30 min)."
fi

touch /var/log/fouta-sync.log
chown www-data:www-data /var/log/fouta-sync.log

log "=== Installation terminée ==="
echo ""
echo "  Site local : http://${FOUTA_LAN_IP}/"
echo "  Admin      : http://${FOUTA_LAN_IP}/admin/"
echo "  Sync test  : cd $FOUTA_WEB_ROOT && sudo -u www-data php scripts/sync_test_ping.php"
echo ""
warn "Étapes manuelles restantes :"
echo "  1. Importer la BDD (dump VPS ou WAMP) si pas fait"
echo "  2. Copier upload/ depuis WAMP : rsync ou scp"
echo "  3. Vérifier config/sync.php (token identique au VPS)"
echo "  4. php scripts/sync_local_to_vps.php --files-priority"
