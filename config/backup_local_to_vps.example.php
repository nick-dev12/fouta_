<?php
/**
 * Sauvegarde serveur local entreprise → VPS (dossier backup, pas le site live).
 * Copiez en config/backup_local_to_vps.php (NE PAS committer).
 *
 * Les sauvegardes vont dans un dossier DÉDIÉ sur le VPS — elles n'écrasent
 * PAS le site de production en ligne (/home/jomas/foutapoidslourds.com).
 */

return [
    'web_root' => '/var/www/fouta',

    // Base locale (ou laissez vide → conn/conn.php)
    'local_db' => [
        'host' => 'localhost',
        'name' => 'fouta_local',
        'user' => 'fouta_user',
        'pass' => 'CHANGEZ_MOI',
    ],

    // SSH vers le VPS (même accès que pull_prod_entreprise)
    'vps_ssh' => [
        'host' => '62.171.190.193',
        'user' => 'root',
        'port' => 22,
        'identity_file' => '/home/fouta/.ssh/id_ed25519',
        // Dossier racine des sauvegardes sur le VPS (créé automatiquement)
        'backup_root' => '/home/jomas/backups/foutasvr',
    ],

    // Contenu à sauvegarder
    'backup' => [
        'database' => true,
        'upload_dir' => true,
        'upload_path' => '/var/www/fouta/upload',
    ],

    // Rétention sur le VPS (jours)
    'retention_days' => 14,

    // Log local
    'log_file' => '/var/log/fouta-backup-vps.log',
];
