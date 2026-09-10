<?php
/**
 * Configuration mise à jour serveur entreprise (foutasvr).
 * Copiez en config/update_entreprise.php (NE PAS committer).
 */

return [
    // Racine de l'application
    'web_root' => '/var/www/fouta',

    // Git
    'git' => [
        'remote' => 'origin',
        'branch' => 'main',
        'repo_url' => 'https://github.com/nick-dev12/fouta_.git',
        // Identité Git (utilisée par install_git_entreprise.sh)
        'user_name' => 'Fouta Serveur Local',
        'user_email' => 'fouta@foutapoidslourds.com',
    ],

    // Fichiers locaux à ne jamais écraser lors d'un git pull
    'protected_files' => [
        'conn/conn.php',
        'config/site.php',
        'config/sync.php',
        'config/pull_prod_entreprise.php',
        'config/update_entreprise.php',
    ],

    // Migrations exécutées à chaque mise à jour (idempotentes)
    'migrations_core' => [
        'migrations/run_migration_production_ajouts.php',
        'migrations/run_add_sync_columns.php',
        'migrations/run_assign_sync_uuids.php',
        // Annulation des tickets de caisse en attente (10/09/2026) : sur foutasvr ET le VPS.
        'migrations/run_caisse_ticket_annulation.php',
        // Trace du prix tapé à la main sur les lignes de ticket (10/09/2026) : sur foutasvr ET le VPS.
        'migrations/run_caisse_lignes_prix_catalogue.php',
        // Statut « payée » des commandes du site (10/09/2026) : sur foutasvr ET le VPS.
        'migrations/run_commandes_statut_paye.php',
        // Clôture de caisse et historique des corrections de paiement (10/09/2026) : sur foutasvr ET le VPS.
        'migrations/run_caisse_cloture.php',
    ],

    // Migrations lourdes / ponctuelles — exclues du mode --all-migrations
    'migrations_exclude_auto' => [
        'run_migration_production_ajouts.php',
        'run_add_sync_columns.php',
        'run_assign_sync_uuids.php',
        'run_optimize_existing_images.php',
        'run_regenerer_qrcodes_barres.php',
        'run_fix_utf8_mojibake.php',
        'run_sync_image_paths_database.php',
    ],

    // Après mise à jour : vérifier la sync local → VPS
    'sync' => [
        'enabled' => true,
        'ping' => true,
        'verify_tables' => false,
        'push_after_update' => false,
    ],

    // Ré-import production (BDD + upload) — désactivé par défaut (écrase le local)
    'pull_production' => [
        'enabled' => false,
        'config_file' => 'config/pull_prod_entreprise.php',
    ],

    // Permissions après mise à jour
    'permissions' => [
        'owner' => 'fouta',
        'group' => 'www-data',
        'upload_mode' => '775',
    ],

    // Recharger Apache après mise à jour
    'reload_apache' => true,
];
