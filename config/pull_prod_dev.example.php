<?php
/**
 * Production → WAMP (développement).
 * Copiez en config/pull_prod_dev.php (NE PAS committer).
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

    // Images : FTP Webuzo (compte jomas, home /home/jomas/)
    'production_files' => [
        'method' => 'auto',
        'host' => '62.171.190.193',
        'port' => 21,
        'user' => 'jomas',
        'pass' => 'CHANGEZ_MOI',
        'passive' => true,
        'remote_path' => '/foutapoidslourds.com/upload',
    ],

    // Repli dump / rsync si le port MySQL 3306 n’est pas ouvert
    'production_ssh' => [
        'host' => '62.171.190.193',
        'user' => 'jomas',
        'port' => 22,
        'identity_file' => '',
        'upload_path' => '/home/jomas/foutapoidslourds.com/upload',
    ],

    'target' => [
        'web_root' => 'C:/wamp64/www/Fouta',
        'site_url' => 'http://localhost/Fouta',
        'mysql_bin' => '',
        'db_host' => 'localhost',
        'db_name' => 'fouta3',
        'db_user' => 'root',
        'db_pass' => '',
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
