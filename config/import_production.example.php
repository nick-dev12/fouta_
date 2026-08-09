<?php
/**
 * Configuration import production → serveur local.
 * Copiez en config/import_production.php et adaptez (NE PAS committer).
 *
 * Site production : https://e.foutapoidslourds.com/
 */

return [
    // Base de données PRODUCTION (Contabo / Webuzo)
    'production_db' => [
        'host' => '62.171.190.193',
        'port' => 3306,
        'name' => 'jomas_fouta',
        'user' => 'jomas_effe',
        'pass' => 'CHANGEZ_MOI',
    ],

    // SSH pour copier le dossier upload/ (même serveur que la BDD en général)
    'production_ssh' => [
        'host' => '62.171.190.193',
        'user' => 'jomas',
        'port' => 22,
        // Chemin ABSOLU du dossier upload sur le serveur production.
        // Exemples Webuzo :
        //   /home/jomas/domains/e.foutapoidslourds.com/public_html/upload
        //   /home/jomas/public_html/upload
        'upload_path' => '/home/jomas/domains/e.foutapoidslourds.com/public_html/upload',
    ],

    // URL publique production (info / logs uniquement)
    'production_site_url' => 'https://e.foutapoidslourds.com',

    // Cible locale (serveur entreprise Ubuntu)
    'local' => [
        'web_root' => '/var/www/fouta',
        'site_url' => 'http://192.168.1.217',
        // Si vide : utilise conn/conn.php
        'db_host' => 'localhost',
        'db_name' => 'fouta_local',
        'db_user' => 'fouta_user',
        'db_pass' => '',
    ],

    // Options d'import
    'import' => [
        // Recréer la base locale avant import (recommandé)
        'recreate_database' => true,
        // Copier upload/ via rsync SSH
        'sync_upload' => true,
        // Exclure barcodes si vous voulez accélérer (false = tout copier)
        'exclude_barcodes' => false,
        // Après import : migrations sync + UUID
        'run_sync_migrations' => true,
        // MySQL : autoriser création triggers
        'fix_mysql_triggers' => true,
    ],
];
