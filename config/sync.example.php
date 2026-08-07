<?php
/**
 * Configuration de synchronisation entre nœuds (local ↔ VPS).
 * Copiez ce fichier en config/sync.php et adaptez les valeurs.
 * NE JAMAIS committer config/sync.php (gitignored).
 */

return [
    // Identifiant unique de ce nœud
    'node_id' => 'local_entreprise',

    // URL de base du nœud distant (sans slash final)
    // Production Fouta VPS : https://infra.goo-bridge.com
    'remote_url' => 'https://www.example.com',

    // Chemin de l'API sync depuis la racine web (DocumentRoot)
    'remote_api_path' => '/sync/api.php',

    // Token partagé identique sur les deux nœuds (min. 32 caractères)
    'remote_api_token' => 'CHANGEZ_MOI_TOKEN_SECRET_TRES_LONG_32_CHARS',

    // Direction de synchronisation :
    // - push_only      : local → VPS uniquement (recommandé magasin local)
    // - pull_only      : VPS → local uniquement
    // - bidirectional  : pull puis push
    'sync_direction' => 'push_only',

    // Priorité en cas d'égalité de sync_updated_at (true = ce nœud gagne)
    'node_priority_on_tie' => false,

    // Tables exclues de la synchronisation
    'excluded_tables' => [
        'sync_log',
        'sync_state',
        'sync_id_map',
        'sync_file_queue',
        'user_password_reset',
        'admin_password_reset',
        'fcm_tokens',
        'panier',
    ],

    // Taille des lots (enregistrements par requête)
    'batch_limit' => 500,

    // Dossier upload relatif à la racine du projet
    'upload_dir' => 'upload',

    // Timeout HTTP vers le nœud distant (secondes)
    'http_timeout' => 120,

    // Vérification SSL (mettre false uniquement en dev local si certificat auto-signé)
    'verify_ssl' => true,

    // Bundle CA optionnel (utilise config/cacert.pem du projet si présent)
    'ca_bundle' => __DIR__ . '/cacert.pem',

    // Connexion BDD distante optionnelle pour scripts/sync_verify.php uniquement
    'remote_db_verify' => [
        'host' => '127.0.0.1',
        'name' => 'nom_base_distante',
        'user' => 'utilisateur',
        'pass' => '',
        'port' => 3306,
    ],
];
