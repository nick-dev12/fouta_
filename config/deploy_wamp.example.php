<?php
/**
 * Déploiement WAMP : production → WAMP → serveur local entreprise.
 * Copiez en config/deploy_wamp.php et adaptez (NE PAS committer).
 *
 * Site production : https://e.foutapoidslourds.com/
 * Serveur local   : http://192.168.1.217 (fplserver)
 */

return [
    // --- Production : base de données ---
    'production_db' => [
        'host' => '62.171.190.193',
        'port' => 3306,
        'name' => 'jomas_fouta',
        'user' => 'jomas_effe',
        'pass' => 'CHANGEZ_MOI',
    ],

    // --- Production : images upload/ (FTP Webuzo — SSH shell désactivé) ---
    'production_files' => [
        // ftp | ftps | skip (garder upload/ WAMP tel quel)
        'method' => 'ftp',
        'host' => '62.171.190.193',
        'port' => 21,
        'user' => 'VOTRE_USER_FTP',
        'pass' => 'VOTRE_PASS_FTP',
        'passive' => true,
        // Chemin FTP vers upload (souvent relatif au home FTP)
        'remote_path' => '/domains/e.foutapoidslourds.com/public_html/upload',
    ],

    'production_site_url' => 'https://e.foutapoidslourds.com',

    // --- WAMP (Windows) ---
    'wamp' => [
        'web_root' => 'C:/wamp64/www/Fouta',
        // Laisser vide pour auto-détection (C:/wamp64/bin/mysql/mysql8.x/bin)
        'mysql_bin' => '',
        'db_host' => 'localhost',
        'db_name' => 'fouta3',
        'db_user' => 'root',
        'db_pass' => '',
    ],

    // --- Serveur local Ubuntu (fplserver) ---
    'local_server' => [
        'host' => '100.120.171.2',
        'user' => 'jomas',
        'port' => 22,
        'web_root' => '/var/www/fouta',
        'site_url' => 'http://192.168.1.217',
        'db_name' => 'fouta_local',
        'db_user' => 'fouta_user',
        'db_pass' => 'CHANGEZ_MOI',
        // 'identity_file' => 'C:/Users/Vous/.ssh/id_ed25519',
    ],

    'options' => [
        // Phase 1 : production → WAMP
        'pull_from_production' => true,
        'recreate_wamp_database' => true,
        'sync_upload_to_wamp' => true,

        // Phase 2 : WAMP → serveur local
        'push_to_local_server' => true,
        'recreate_server_database' => true,
        'sync_upload_to_server' => true,
        'run_sync_migrations_on_server' => true,
    ],
];
