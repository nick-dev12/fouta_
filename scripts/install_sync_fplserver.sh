#!/bin/bash
# Installe config/sync.php sur fplserver (local_entreprise → production)
# Usage sur fplserver :
#   cd /var/www/fouta && git pull origin main && bash scripts/install_sync_fplserver.sh

set -euo pipefail

WEB_ROOT="${FOUTA_WEB_ROOT:-/var/www/fouta}"
SYNC_TOKEN="${FOUTA_SYNC_TOKEN:-FoutaSync2026DevTokenChangeInProduction!}"
REMOTE_URL="${FOUTA_REMOTE_URL:-https://e.foutapoidslourds.com}"

cd "$WEB_ROOT"

cat > config/sync.php <<PHP
<?php
\$cfg = [
    'node_id' => 'local_entreprise',
    'remote_url' => '${REMOTE_URL}',
    'remote_api_path' => '/sync/api.php',
    'remote_api_token' => '${SYNC_TOKEN}',
    'sync_direction' => 'push_only',
    'node_priority_on_tie' => true,
    'excluded_tables' => [
        'sync_log', 'sync_state', 'sync_id_map', 'sync_file_queue',
        'user_password_reset', 'admin_password_reset', 'fcm_tokens', 'panier',
    ],
    'batch_limit' => 500,
    'sync_include_files' => true,
    'upload_dirs' => ['upload'],
    'upload_dirs_priority' => [
        'upload/produits', 'upload/categories', 'upload/slider', 'upload/logos',
        'upload/section4', 'upload/trending', 'upload/employes_photos',
        'upload/employes_qr', 'upload/employes_documents', 'upload/videos',
        'upload/commandes-personnalisees',
    ],
    'files_batch_count' => 8,
    'files_batch_max_bytes' => 6291456,
    'files_max_per_run' => 0,
    'upload_file_extensions' => [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'avif',
        'pdf', 'mp4', 'webm', 'mov',
    ],
    'http_timeout' => 300,
    'verify_ssl' => true,
];
\$cfg['ca_bundle'] = __DIR__ . '/cacert.pem';
return \$cfg;
PHP

chmod 640 config/sync.php
echo "config/sync.php créé (local_entreprise → ${REMOTE_URL})"

if [[ -f config/cacert.pem ]]; then
    echo "cacert.pem présent."
else
    echo "Téléchargement cacert.pem..."
    curl -fsSL -o config/cacert.pem https://curl.se/ca/cacert.pem
fi

echo "Test ping..."
sudo -u www-data php scripts/sync_test_ping.php

CRON_LINE="*/30 * * * * www-data /usr/bin/php ${WEB_ROOT}/scripts/sync_local_to_vps.php >> /var/log/fouta-sync.log 2>&1"
if ! grep -qF 'sync_local_to_vps.php' /etc/crontab 2>/dev/null; then
    echo "$CRON_LINE" | sudo tee -a /etc/crontab
    sudo touch /var/log/fouta-sync.log
    sudo chown www-data:www-data /var/log/fouta-sync.log
    echo "Cron sync ajouté."
fi

echo "Terminé."
