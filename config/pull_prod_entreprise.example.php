<?php
/**
 * Production → serveur local entreprise (fplserver).
 * Copiez en config/pull_prod_entreprise.php (NE PAS committer).
 *
 * À exécuter SUR le serveur de l’entreprise.
 */

return [
    'production_site_url' => 'https://e.foutapoidslourds.com',

    'production_db' => [
        'host' => '62.171.190.193',
        'port' => 3306,
        'name' => 'jomas_fouta',
        'user' => 'jomas_effe',
        'pass' => 'CHANGEZ_MOI',
    ],

    // FTP (si disponible) — sinon rsync SSH via production_ssh
    'production_files' => [
        'method' => 'auto',
        'host' => '62.171.190.193',
        'port' => 21,
        'user' => 'jomas',
        'pass' => 'CHANGEZ_MOI',
        'passive' => true,
        'remote_path' => '/foutapoidslourds.com/upload',
    ],

    'production_ssh' => [
        'host' => '62.171.190.193',
        'user' => 'jomas',
        'port' => 22,
        'identity_file' => '/home/jomas/.ssh/id_ed25519',
        'upload_path' => '/home/jomas/foutapoidslourds.com/upload',
    ],

    'target' => [
        'web_root' => '/var/www/fouta',
        'site_url' => 'http://192.168.1.217',
        'mysql_bin' => '',
        'db_host' => 'localhost',
        'db_name' => 'fouta_local',
        'db_user' => 'fouta_user',
        'db_pass' => 'CHANGEZ_MOI',
    ],

    'options' => [
        'recreate_database' => true,
        'sync_upload' => true,
        'wipe_upload_before_pull' => true,
        'exclude_barcodes' => false,
        'write_site_php' => true,
        'adapt_htaccess_for_http' => true,
    ],
];
