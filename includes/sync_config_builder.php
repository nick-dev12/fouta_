<?php
/**
 * Génération des fichiers config/sync.php par profil de nœud.
 */

function sync_config_defaults() {
    return [
        'remote_api_path' => '/sync/api.php',
        'node_priority_on_tie' => false,
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
        'batch_limit' => 500,
        'sync_include_files' => true,
        'upload_dirs' => ['upload'],
        'upload_dirs_priority' => [
            'upload/produits',
            'upload/categories',
            'upload/slider',
            'upload/logos',
            'upload/section4',
            'upload/trending',
            'upload/employes_photos',
            'upload/employes_qr',
            'upload/employes_documents',
            'upload/videos',
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
}

function sync_config_build(array $profile, $remote_url, $remote_api_token) {
    $defaults = sync_config_defaults();
    $config = array_merge($defaults, $profile, [
        'remote_url' => rtrim((string) $remote_url, '/'),
        'remote_api_token' => (string) $remote_api_token,
    ]);
    $direction = strtolower((string) ($config['sync_direction'] ?? 'push_only'));
    if (!in_array($direction, ['push_only', 'pull_only', 'bidirectional'], true)) {
        $config['sync_direction'] = 'push_only';
    }
    return $config;
}

function sync_config_to_php(array $config) {
    $export = var_export($config, true);
    return "<?php\n/**\n * Configuration sync — généré automatiquement.\n * NE PAS committer.\n */\n\n"
        . '$cfg = ' . $export . ";\n"
        . "\$cfg['ca_bundle'] = __DIR__ . '/cacert.pem';\n\n"
        . "return \$cfg;\n";
}

function sync_config_write($path, array $config) {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return file_put_contents($path, sync_config_to_php($config)) !== false;
}

function sync_config_wamp_profile($node_id = 'dev_wamp') {
    return sync_config_build([
        'node_id' => $node_id,
        'sync_direction' => 'bidirectional',
        'node_priority_on_tie' => false,
    ], '', '');
}

function sync_config_local_server_profile($node_id = 'local_entreprise') {
    return sync_config_build([
        'node_id' => $node_id,
        'sync_direction' => 'push_only',
        'node_priority_on_tie' => true,
    ], '', '');
}

function sync_config_apply_remote_url_token(array $config, $remote_url, $token) {
    $config['remote_url'] = rtrim((string) $remote_url, '/');
    $config['remote_api_token'] = (string) $token;
    return $config;
}
