<?php
/**
 * Fonctions de synchronisation bidirectionnelle (PHP procédural).
 */

require_once __DIR__ . '/sync_registry.php';

if (!function_exists('sync_load_config')) {
    function sync_load_config() {
        static $config = null;
        if ($config !== null) {
            return $config;
        }

        $path = dirname(__DIR__) . '/config/sync.php';
        if (!is_file($path)) {
            throw new RuntimeException('Fichier config/sync.php introuvable. Copiez config/sync.example.php.');
        }

        $loaded = require $path;
        if (!is_array($loaded)) {
            throw new RuntimeException('config/sync.php doit retourner un tableau.');
        }

        $defaults = require dirname(__DIR__) . '/config/sync.example.php';
        $config = array_merge($defaults, $loaded);
        return $config;
    }
}

if (!function_exists('sync_generate_uuid')) {
    function sync_generate_uuid() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('sync_bootstrap_connection')) {
    function sync_bootstrap_connection(PDO $db, $config) {
        $node_id = $config['node_id'] ?? 'local';
        $db->exec('SET @sync_applying = 0');
        $db->exec("SET @sync_node_id = " . $db->quote($node_id));
    }
}

if (!function_exists('sync_ensure_infrastructure')) {
    function sync_ensure_infrastructure(PDO $db) {
        $sql_file = dirname(__DIR__) . '/migrations/create_sync_infrastructure.sql';
        if (!is_file($sql_file)) {
            throw new RuntimeException('Fichier create_sync_infrastructure.sql introuvable.');
        }

        $sql = file_get_contents($sql_file);
        $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);
        $parts = preg_split('/;\s*\n/', $sql);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || stripos($part, 'SET NAMES') === 0) {
                if ($part !== '') {
                    $db->exec($part);
                }
                continue;
            }
            try {
                $db->exec($part);
            } catch (PDOException $e) {
                if (stripos($e->getMessage(), 'already exists') === false) {
                    throw $e;
                }
            }
        }
    }
}

if (!function_exists('sync_add_columns_to_tables')) {
    function sync_add_columns_to_tables(PDO $db, $config = null) {
        $tables = sync_registry_discover_tables($db, $config);
        $added = 0;
        $skipped = 0;

        foreach ($tables as $table) {
            if (sync_registry_has_sync_columns($db, $table)) {
                $skipped++;
                continue;
            }

            $alter = "ALTER TABLE `$table`
                ADD COLUMN `sync_uuid` CHAR(36) NULL DEFAULT NULL,
                ADD COLUMN `sync_updated_at` DATETIME NULL DEFAULT NULL,
                ADD COLUMN `sync_deleted_at` DATETIME NULL DEFAULT NULL,
                ADD COLUMN `sync_origin_node` VARCHAR(64) NULL DEFAULT NULL,
                ADD UNIQUE KEY `idx_{$table}_sync_uuid` (`sync_uuid`),
                ADD KEY `idx_{$table}_sync_updated` (`sync_updated_at`)";
            try {
                $db->exec($alter);
                $added++;
            } catch (PDOException $e) {
                if (stripos($e->getMessage(), 'Duplicate column') !== false) {
                    $skipped++;
                } else {
                    throw $e;
                }
            }
        }

        return ['added' => $added, 'skipped' => $skipped, 'tables' => count($tables)];
    }
}

if (!function_exists('sync_table_primary_key')) {
    function sync_table_primary_key(PDO $db, $table) {
        $db_name = $db->query('SELECT DATABASE()')->fetchColumn();
        $stmt = $db->prepare(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_KEY = 'PRI'
             ORDER BY ORDINAL_POSITION ASC LIMIT 1"
        );
        $stmt->execute([$db_name, $table]);
        $pk = $stmt->fetchColumn();
        return $pk !== false ? $pk : 'id';
    }
}

if (!function_exists('sync_assign_missing_uuids')) {
    function sync_assign_missing_uuids(PDO $db, $config = null) {
        $config = $config ?: sync_load_config();
        sync_bootstrap_connection($db, $config);

        $tables = sync_registry_discover_tables($db, $config);
        $updated = 0;

        foreach ($tables as $table) {
            if (!sync_registry_has_sync_columns($db, $table)) {
                continue;
            }

            $node_id = $config['node_id'] ?? 'local';
            $sql = "UPDATE `$table` SET
                sync_uuid = UUID(),
                sync_updated_at = COALESCE(sync_updated_at, NOW()),
                sync_origin_node = COALESCE(sync_origin_node, " . $db->quote($node_id) . ")
                WHERE sync_uuid IS NULL OR sync_uuid = ''";
            try {
                $count = $db->exec($sql);
                if ($count !== false) {
                    $updated += (int) $count;
                }
            } catch (PDOException $e) {
                if (stripos($e->getMessage(), 'Duplicate') !== false) {
                    $pk = sync_table_primary_key($db, $table);
                    if (!sync_table_has_column($db, $table, $pk)) {
                        throw $e;
                    }
                    $stmt = $db->query("SELECT `$pk` FROM `$table` WHERE sync_uuid IS NULL OR sync_uuid = ''");
                    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
                    foreach ($rows as $pk_value) {
                        $uuid = sync_generate_uuid();
                        $upd = $db->prepare(
                            "UPDATE `$table` SET sync_uuid = ?, sync_updated_at = COALESCE(sync_updated_at, NOW()), sync_origin_node = COALESCE(sync_origin_node, ?) WHERE `$pk` = ?"
                        );
                        $upd->execute([$uuid, $node_id, $pk_value]);
                        $updated++;
                    }
                } else {
                    throw $e;
                }
            }
        }

        return $updated;
    }
}

if (!function_exists('sync_create_triggers_for_table')) {
    function sync_create_triggers_for_table(PDO $db, $table) {
        $db->exec("DROP TRIGGER IF EXISTS `tr_{$table}_sync_insert`");
        $db->exec("DROP TRIGGER IF EXISTS `tr_{$table}_sync_update`");

        $insert_sql = "
            CREATE TRIGGER `tr_{$table}_sync_insert`
            BEFORE INSERT ON `$table`
            FOR EACH ROW
            BEGIN
                IF NEW.sync_uuid IS NULL OR NEW.sync_uuid = '' THEN
                    SET NEW.sync_uuid = UUID();
                END IF;
                IF NEW.sync_updated_at IS NULL THEN
                    SET NEW.sync_updated_at = NOW();
                END IF;
                IF @sync_applying IS NULL OR @sync_applying = 0 THEN
                    IF NEW.sync_origin_node IS NULL OR NEW.sync_origin_node = '' THEN
                        SET NEW.sync_origin_node = @sync_node_id;
                    END IF;
                END IF;
            END
        ";
        $update_sql = "
            CREATE TRIGGER `tr_{$table}_sync_update`
            BEFORE UPDATE ON `$table`
            FOR EACH ROW
            BEGIN
                IF @sync_applying IS NULL OR @sync_applying = 0 THEN
                    SET NEW.sync_updated_at = NOW();
                    SET NEW.sync_origin_node = @sync_node_id;
                END IF;
            END
        ";

        $db->exec($insert_sql);
        $db->exec($update_sql);
    }
}

if (!function_exists('sync_create_all_triggers')) {
    function sync_create_all_triggers(PDO $db, $config = null) {
        $config = $config ?: sync_load_config();
        sync_bootstrap_connection($db, $config);

        $tables = sync_registry_discover_tables($db, $config);
        $created = 0;
        foreach ($tables as $table) {
            if (!sync_registry_has_sync_columns($db, $table)) {
                continue;
            }
            sync_create_triggers_for_table($db, $table);
            $created++;
        }
        return $created;
    }
}

if (!function_exists('sync_get_state')) {
    function sync_get_state(PDO $db, $key, $default = null) {
        $stmt = $db->prepare('SELECT state_value FROM sync_state WHERE state_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : $value;
    }
}

if (!function_exists('sync_set_state')) {
    function sync_set_state(PDO $db, $key, $value) {
        $stmt = $db->prepare(
            'INSERT INTO sync_state (state_key, state_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE state_value = VALUES(state_value), updated_at = NOW()'
        );
        $stmt->execute([$key, $value]);
    }
}

if (!function_exists('sync_log_entry')) {
    function sync_log_entry(PDO $db, array $data) {
        $stmt = $db->prepare(
            'INSERT INTO sync_log (direction, table_name, records_count, conflicts_count, status, message, node_id, remote_node_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['direction'] ?? 'push',
            $data['table_name'] ?? null,
            (int) ($data['records_count'] ?? 0),
            (int) ($data['conflicts_count'] ?? 0),
            $data['status'] ?? 'success',
            $data['message'] ?? null,
            $data['node_id'] ?? null,
            $data['remote_node_id'] ?? null,
        ]);
        return (int) $db->lastInsertId();
    }
}

if (!function_exists('sync_touch_record')) {
    function sync_touch_record(PDO $db, $table, $id) {
        if (!sync_registry_has_sync_columns($db, $table)) {
            return false;
        }
        $pk_col = sync_table_primary_key($db, $table);
        $stmt = $db->prepare("UPDATE `$table` SET sync_updated_at = NOW() WHERE `$pk_col` = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}

if (!function_exists('sync_table_has_column')) {
    function sync_table_has_column(PDO $db, $table, $column) {
        $columns = sync_registry_table_columns($db, $table);
        foreach ($columns as $col) {
            if ($col['COLUMN_NAME'] === $column) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('sync_build_fk_uuid_map')) {
    function sync_build_fk_uuid_map(PDO $db, $table, array $row) {
        $map = [];
        foreach (sync_registry_foreign_keys($db, $table) as $fk) {
            $col = $fk['COLUMN_NAME'];
            $ref_table = $fk['REFERENCED_TABLE_NAME'];
            if (!isset($row[$col]) || $row[$col] === null || $row[$col] === '') {
                continue;
            }
            if (!sync_registry_has_sync_columns($db, $ref_table)) {
                continue;
            }
            $pk_col = sync_table_primary_key($db, $ref_table);
            $stmt = $db->prepare("SELECT sync_uuid FROM `$ref_table` WHERE `$pk_col` = ? LIMIT 1");
            $stmt->execute([(int) $row[$col]]);
            $uuid = $stmt->fetchColumn();
            if ($uuid) {
                $map[$col] = [
                    'ref_table' => $ref_table,
                    'sync_uuid' => $uuid,
                ];
            }
        }
        return $map;
    }
}

if (!function_exists('sync_resolve_fk_value')) {
    function sync_resolve_fk_value(PDO $db, $ref_table, $sync_uuid) {
        if (!$sync_uuid) {
            return null;
        }
        $pk_col = sync_table_primary_key($db, $ref_table);
        $stmt = $db->prepare("SELECT `$pk_col` FROM `$ref_table` WHERE sync_uuid = ? AND (sync_deleted_at IS NULL) LIMIT 1");
        $stmt->execute([$sync_uuid]);
        $id = $stmt->fetchColumn();
        return $id !== false ? $id : null;
    }
}

if (!function_exists('sync_store_id_map')) {
    function sync_store_id_map(PDO $db, $table, $sync_uuid, $local_id) {
        $stmt = $db->prepare(
            'INSERT INTO sync_id_map (table_name, sync_uuid, local_id) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE local_id = VALUES(local_id)'
        );
        $stmt->execute([$table, $sync_uuid, (int) $local_id]);
    }
}

if (!function_exists('sync_get_pending_records')) {
    function sync_get_pending_records(PDO $db, $table, $since, $limit = 500, $only_local_origin = false) {
        if (!sync_registry_has_sync_columns($db, $table)) {
            return [];
        }

        $sql = "SELECT * FROM `$table` WHERE sync_updated_at IS NOT NULL";
        $params = [];
        if ($since) {
            $sql .= ' AND sync_updated_at > ?';
            $params[] = $since;
        }
        if ($only_local_origin) {
            $node_id = sync_load_config()['node_id'] ?? '';
            if ($node_id !== '') {
                $sql .= ' AND (sync_origin_node = ? OR sync_origin_node IS NULL OR sync_origin_node = \'\')';
                $params[] = $node_id;
            }
        }
        $pk_col = sync_table_primary_key($db, $table);
        $order_col = sync_table_has_column($db, $table, 'id') ? 'id' : $pk_col;
        $sql .= " ORDER BY sync_updated_at ASC, `$order_col` ASC LIMIT " . (int) $limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $batch = [];
        foreach ($rows as $row) {
            $sync_cols = ['sync_uuid', 'sync_updated_at', 'sync_deleted_at', 'sync_origin_node'];
            $meta = [];
            foreach ($sync_cols as $col) {
                if (array_key_exists($col, $row)) {
                    $meta[$col] = $row[$col];
                    unset($row[$col]);
                }
            }
            $batch[] = [
                'table' => $table,
                'sync_uuid' => $meta['sync_uuid'] ?? null,
                'sync_updated_at' => $meta['sync_updated_at'] ?? null,
                'sync_deleted_at' => $meta['sync_deleted_at'] ?? null,
                'sync_origin_node' => $meta['sync_origin_node'] ?? null,
                'data' => $row,
                'fk_uuids' => sync_build_fk_uuid_map($db, $table, $row),
            ];
        }

        return $batch;
    }
}

if (!function_exists('sync_apply_record')) {
    function sync_apply_record(PDO $db, array $item, array $config, &$stats) {
        $table = $item['table'] ?? '';
        $sync_uuid = $item['sync_uuid'] ?? '';
        if ($table === '' || $sync_uuid === '') {
            $stats['errors']++;
            return false;
        }
        if (!sync_registry_has_sync_columns($db, $table)) {
            $stats['errors']++;
            return false;
        }

        $remote_updated = $item['sync_updated_at'] ?? null;
        $remote_deleted = $item['sync_deleted_at'] ?? null;
        $remote_node = $item['sync_origin_node'] ?? 'remote';
        $data = is_array($item['data'] ?? null) ? $item['data'] : [];
        $fk_uuids = is_array($item['fk_uuids'] ?? null) ? $item['fk_uuids'] : [];

        $pk_col = sync_table_primary_key($db, $table);
        unset($data[$pk_col]);
        if ($pk_col !== 'id') {
            unset($data['id']);
        }

        foreach ($fk_uuids as $col => $fk) {
            if (!is_array($fk)) {
                continue;
            }
            $resolved = sync_resolve_fk_value($db, $fk['ref_table'] ?? '', $fk['sync_uuid'] ?? '');
            if ($resolved === null && !empty($data[$col])) {
                $stats['errors']++;
                return false;
            }
            if ($resolved !== null) {
                $data[$col] = $resolved;
            }
        }

        $stmt = $db->prepare("SELECT `$pk_col`, sync_updated_at FROM `$table` WHERE sync_uuid = ? LIMIT 1");
        $stmt->execute([$sync_uuid]);
        $local = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($local) {
            $local_pk = $local[$pk_col];
            $local_updated = $local['sync_updated_at'] ?? null;
            if ($local_updated && $remote_updated && strtotime($local_updated) > strtotime($remote_updated)) {
                $stats['conflicts']++;
                return false;
            }
            if ($local_updated && $remote_updated && strtotime($local_updated) === strtotime($remote_updated)) {
                $local_node_wins = !empty($config['node_priority_on_tie']);
                if ($local_node_wins) {
                    $stats['conflicts']++;
                    return false;
                }
            }

            $data['sync_uuid'] = $sync_uuid;
            $data['sync_updated_at'] = $remote_updated;
            $data['sync_deleted_at'] = $remote_deleted;
            $data['sync_origin_node'] = $remote_node;

            $sets = [];
            $values = [];
            foreach ($data as $col => $val) {
                if (!sync_table_has_column($db, $table, $col)) {
                    continue;
                }
                $sets[] = "`$col` = ?";
                $values[] = $val;
            }
            if (!$sets) {
                return false;
            }
            $values[] = $sync_uuid;

            $db->exec('SET @sync_applying = 1');
            $sql = "UPDATE `$table` SET " . implode(', ', $sets) . ' WHERE sync_uuid = ?';
            $upd = $db->prepare($sql);
            $upd->execute($values);
            $db->exec('SET @sync_applying = 0');

            sync_store_id_map($db, $table, $sync_uuid, is_numeric($local_pk) ? (int) $local_pk : $local_pk);
            $stats['updated']++;
            return true;
        }

        $columns = sync_registry_table_columns($db, $table);
        $auto_increment = false;
        foreach ($columns as $col) {
            if ($col['COLUMN_NAME'] === 'id' && stripos($col['EXTRA'] ?? '', 'auto_increment') !== false) {
                $auto_increment = true;
                break;
            }
        }

        $insert_data = $data;
        $insert_data['sync_uuid'] = $sync_uuid;
        $insert_data['sync_updated_at'] = $remote_updated ?: date('Y-m-d H:i:s');
        $insert_data['sync_deleted_at'] = $remote_deleted;
        $insert_data['sync_origin_node'] = $remote_node;

        $fields = [];
        $placeholders = [];
        $values = [];
        foreach ($insert_data as $col => $val) {
            if (!sync_table_has_column($db, $table, $col)) {
                continue;
            }
            $fields[] = "`$col`";
            $placeholders[] = '?';
            $values[] = $val;
        }

        if (!$fields) {
            $stats['errors']++;
            return false;
        }

        $db->exec('SET @sync_applying = 1');
        $sql = 'INSERT INTO `' . $table . '` (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $ins = $db->prepare($sql);
        $ins->execute($values);
        $new_id = (int) $db->lastInsertId();
        $db->exec('SET @sync_applying = 0');

        sync_store_id_map($db, $table, $sync_uuid, $new_id);
        $stats['inserted']++;
        return true;
    }
}

if (!function_exists('sync_apply_batch')) {
    function sync_apply_batch(PDO $db, array $batch, array $config) {
        $stats = ['inserted' => 0, 'updated' => 0, 'conflicts' => 0, 'errors' => 0];
        sync_bootstrap_connection($db, $config);

        $by_table = [];
        foreach ($batch as $item) {
            $table = $item['table'] ?? 'unknown';
            if (!isset($by_table[$table])) {
                $by_table[$table] = [];
            }
            $by_table[$table][] = $item;
        }

        $ordered = sync_registry_sort_tables($db, $config);
        foreach ($ordered as $table) {
            if (empty($by_table[$table])) {
                continue;
            }
            foreach ($by_table[$table] as $item) {
                sync_apply_record($db, $item, $config, $stats);
            }
            unset($by_table[$table]);
        }

        foreach ($by_table as $table => $items) {
            foreach ($items as $item) {
                sync_apply_record($db, $item, $config, $stats);
            }
        }

        return $stats;
    }
}

if (!function_exists('sync_remote_request')) {
    function sync_remote_request($action, array $payload, array $config = null) {
        $config = $config ?: sync_load_config();
        $url = rtrim($config['remote_url'] ?? '', '/') . '/sync/api.php?action=' . urlencode($action);

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Impossible d\'encoder le payload JSON.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extension PHP curl requise pour la synchronisation.');
        }

        $ch = curl_init($url);
        $verify_ssl = !empty($config['verify_ssl']);
        $ca_bundle = $config['ca_bundle'] ?? (dirname(__DIR__) . '/config/cacert.pem');
        $curl_opts = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) ($config['http_timeout'] ?? 120),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'Authorization: Bearer ' . ($config['remote_api_token'] ?? ''),
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => $verify_ssl,
            CURLOPT_SSL_VERIFYHOST => $verify_ssl ? 2 : 0,
        ];
        if ($verify_ssl && is_file($ca_bundle)) {
            $curl_opts[CURLOPT_CAINFO] = $ca_bundle;
        }
        curl_setopt_array($ch, $curl_opts);

        $response = curl_exec($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Erreur cURL : ' . $curl_error);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Réponse JSON invalide (HTTP ' . $http_code . ').');
        }

        if ($http_code >= 400 || empty($decoded['success'])) {
            $message = $decoded['error'] ?? ('HTTP ' . $http_code);
            throw new RuntimeException('Sync API : ' . $message);
        }

        return $decoded;
    }
}

if (!function_exists('sync_pull_table')) {
    function sync_pull_table(PDO $db, $table, array $config, $since = null) {
        $limit = (int) ($config['batch_limit'] ?? 500);
        $total = 0;
        $conflicts = 0;
        $cursor = $since ?: sync_get_state($db, 'last_pull_since', '1970-01-01 00:00:00');
        $max_seen = $cursor;

        do {
            $response = sync_remote_request('pull', [
                'since' => $cursor,
                'tables' => [$table],
                'limit' => $limit,
            ], $config);

            $batch = $response['batch'] ?? [];
            if (!$batch) {
                break;
            }

            $stats = sync_apply_batch($db, $batch, $config);
            $total += ($stats['inserted'] + $stats['updated']);
            $conflicts += $stats['conflicts'];

            foreach ($batch as $item) {
                $ts = $item['sync_updated_at'] ?? null;
                if ($ts && strtotime($ts) > strtotime($max_seen)) {
                    $max_seen = $ts;
                }
            }

            if (count($batch) < $limit) {
                break;
            }
            $cursor = $max_seen;
        } while (true);

        return ['records' => $total, 'conflicts' => $conflicts, 'max_seen' => $max_seen];
    }
}

if (!function_exists('sync_pull')) {
    function sync_pull(PDO $db, array $config = null, $dry_run = false) {
        $config = $config ?: sync_load_config();
        sync_bootstrap_connection($db, $config);
        sync_ensure_infrastructure($db);

        if ($dry_run) {
            return ['dry_run' => true, 'message' => 'Pull simulé — aucune écriture.'];
        }

        $since = sync_get_state($db, 'last_pull_since', '1970-01-01 00:00:00');
        $tables = sync_registry_sort_tables($db, $config);
        $total_records = 0;
        $total_conflicts = 0;
        $max_seen = $since;

        foreach ($tables as $table) {
            $result = sync_pull_table($db, $table, $config, $since);
            $total_records += $result['records'];
            $total_conflicts += $result['conflicts'];
            if ($result['max_seen'] && strtotime($result['max_seen']) > strtotime($max_seen)) {
                $max_seen = $result['max_seen'];
            }
            if ($result['records'] > 0) {
                sync_log_entry($db, [
                    'direction' => 'pull',
                    'table_name' => $table,
                    'records_count' => $result['records'],
                    'conflicts_count' => $result['conflicts'],
                    'status' => $result['conflicts'] > 0 ? 'partial' : 'success',
                    'node_id' => $config['node_id'],
                    'remote_node_id' => 'remote',
                ]);
            }
        }

        if ($max_seen !== $since) {
            sync_set_state($db, 'last_pull_since', $max_seen);
        }

        sync_log_entry($db, [
            'direction' => 'pull',
            'table_name' => null,
            'records_count' => $total_records,
            'conflicts_count' => $total_conflicts,
            'status' => $total_conflicts > 0 ? 'partial' : 'success',
            'message' => 'Pull terminé',
            'node_id' => $config['node_id'],
        ]);

        return [
            'records' => $total_records,
            'conflicts' => $total_conflicts,
            'since' => $since,
            'max_seen' => $max_seen,
        ];
    }
}

if (!function_exists('sync_push_table')) {
    function sync_push_table(PDO $db, $table, array $config, $since = null) {
        $limit = (int) ($config['batch_limit'] ?? 500);
        $cursor = $since ?: sync_get_state($db, 'last_push_since', '1970-01-01 00:00:00');
        $total = 0;
        $conflicts = 0;
        $max_seen = $cursor;

        do {
            $batch = sync_get_pending_records($db, $table, $cursor, $limit, true);
            if (!$batch) {
                break;
            }

            $response = sync_remote_request('push', [
                'node_id' => $config['node_id'],
                'batch' => $batch,
            ], $config);

            $stats = $response['stats'] ?? [];
            $total += (int) (($stats['inserted'] ?? 0) + ($stats['updated'] ?? 0));
            $conflicts += (int) ($stats['conflicts'] ?? 0);

            foreach ($batch as $item) {
                $ts = $item['sync_updated_at'] ?? null;
                if ($ts && strtotime($ts) > strtotime($max_seen)) {
                    $max_seen = $ts;
                }
            }

            if (count($batch) < $limit) {
                break;
            }
            $cursor = $max_seen;
        } while (true);

        return ['records' => $total, 'conflicts' => $conflicts, 'max_seen' => $max_seen];
    }
}

if (!function_exists('sync_push')) {
    function sync_push(PDO $db, array $config = null, $dry_run = false) {
        $config = $config ?: sync_load_config();
        sync_bootstrap_connection($db, $config);
        sync_ensure_infrastructure($db);

        if ($dry_run) {
            return ['dry_run' => true, 'message' => 'Push simulé — aucun envoi.'];
        }

        $since = sync_get_state($db, 'last_push_since', '1970-01-01 00:00:00');
        $tables = sync_registry_sort_tables($db, $config);
        $total_records = 0;
        $total_conflicts = 0;
        $max_seen = $since;

        foreach ($tables as $table) {
            $result = sync_push_table($db, $table, $config, $since);
            $total_records += $result['records'];
            $total_conflicts += $result['conflicts'];
            if ($result['max_seen'] && strtotime($result['max_seen']) > strtotime($max_seen)) {
                $max_seen = $result['max_seen'];
            }
            if ($result['records'] > 0) {
                sync_log_entry($db, [
                    'direction' => 'push',
                    'table_name' => $table,
                    'records_count' => $result['records'],
                    'conflicts_count' => $result['conflicts'],
                    'status' => $result['conflicts'] > 0 ? 'partial' : 'success',
                    'node_id' => $config['node_id'],
                ]);
            }
        }

        if ($max_seen !== $since) {
            sync_set_state($db, 'last_push_since', $max_seen);
        }

        sync_log_entry($db, [
            'direction' => 'push',
            'table_name' => null,
            'records_count' => $total_records,
            'conflicts_count' => $total_conflicts,
            'status' => $total_conflicts > 0 ? 'partial' : 'success',
            'message' => 'Push terminé',
            'node_id' => $config['node_id'],
        ]);

        return [
            'records' => $total_records,
            'conflicts' => $total_conflicts,
            'since' => $since,
            'max_seen' => $max_seen,
        ];
    }
}

if (!function_exists('sync_run')) {
    function sync_run(PDO $db, array $config = null, $dry_run = false) {
        $pull = sync_pull($db, $config, $dry_run);
        $push = sync_push($db, $config, $dry_run);
        return ['pull' => $pull, 'push' => $push];
    }
}

if (!function_exists('sync_verify_remote_db')) {
    function sync_verify_remote_db(PDO $local_db, array $config = null) {
        $config = $config ?: sync_load_config();
        if (empty($config['remote_db_verify']) || !is_array($config['remote_db_verify'])) {
            throw new RuntimeException('remote_db_verify non configuré dans config/sync.php');
        }

        $r = $config['remote_db_verify'];
        $port = (int) ($r['port'] ?? 3306);
        $remote = new PDO(
            'mysql:host=' . $r['host'] . ';port=' . $port . ';dbname=' . $r['name'] . ';charset=utf8mb4',
            $r['user'],
            $r['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $tables = sync_registry_sort_tables($local_db, $config);
        $report = [];
        foreach ($tables as $table) {
            $local_count = (int) $local_db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            try {
                $remote_count = (int) $remote->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            } catch (PDOException $e) {
                $remote_count = -1;
            }
            $report[] = [
                'table' => $table,
                'local' => $local_count,
                'remote' => $remote_count,
                'diff' => $remote_count >= 0 ? ($local_count - $remote_count) : null,
            ];
        }
        return $report;
    }
}

if (!function_exists('sync_files_scan')) {
    function sync_files_scan(array $config = null) {
        $config = $config ?: sync_load_config();
        $upload_dir = dirname(__DIR__) . '/' . ltrim($config['upload_dir'] ?? 'upload', '/');
        $files = [];
        if (!is_dir($upload_dir)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($upload_dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $full = $file->getPathname();
            $relative = ltrim(str_replace('\\', '/', substr($full, strlen(dirname(__DIR__) . '/'))), '/');
            $files[] = [
                'relative_path' => $relative,
                'md5' => md5_file($full),
                'size' => filesize($full),
                'mtime' => date('Y-m-d H:i:s', filemtime($full)),
            ];
        }
        return $files;
    }
}

if (!function_exists('sync_files_push')) {
    function sync_files_push(PDO $db, array $config = null, $dry_run = false) {
        $config = $config ?: sync_load_config();
        $files = sync_files_scan($config);
        $since = sync_get_state($db, 'last_files_push_since', '1970-01-01 00:00:00');
        $pushed = 0;

        foreach ($files as $file) {
            if (($file['mtime'] ?? '') <= $since) {
                continue;
            }
            if ($dry_run) {
                $pushed++;
                continue;
            }

            $full = dirname(__DIR__) . '/' . $file['relative_path'];
            $content = file_get_contents($full);
            if ($content === false) {
                continue;
            }

            sync_remote_request('file_push', [
                'relative_path' => $file['relative_path'],
                'md5' => $file['md5'],
                'content_base64' => base64_encode($content),
            ], $config);
            $pushed++;
        }

        if (!$dry_run && $pushed > 0) {
            sync_set_state($db, 'last_files_push_since', date('Y-m-d H:i:s'));
            sync_log_entry($db, [
                'direction' => 'files',
                'records_count' => $pushed,
                'status' => 'success',
                'message' => 'Fichiers upload synchronisés',
                'node_id' => $config['node_id'],
            ]);
        }

        return ['files' => $pushed];
    }
}

if (!function_exists('sync_api_handle_pull')) {
    function sync_api_handle_pull(PDO $db, array $input, array $config) {
        $since = $input['since'] ?? '1970-01-01 00:00:00';
        $limit = (int) ($input['limit'] ?? ($config['batch_limit'] ?? 500));
        $tables = $input['tables'] ?? sync_registry_sort_tables($db, $config);
        if (!is_array($tables)) {
            $tables = [$tables];
        }

        $batch = [];
        foreach ($tables as $table) {
            $records = sync_get_pending_records($db, $table, $since, $limit);
            foreach ($records as $record) {
                $batch[] = $record;
                if (count($batch) >= $limit) {
                    break 2;
                }
            }
        }

        return [
            'success' => true,
            'batch' => $batch,
            'count' => count($batch),
            'node_id' => $config['node_id'],
        ];
    }
}

if (!function_exists('sync_api_handle_push')) {
    function sync_api_handle_push(PDO $db, array $input, array $config) {
        $batch = $input['batch'] ?? [];
        if (!is_array($batch)) {
            throw new InvalidArgumentException('batch invalide');
        }
        $stats = sync_apply_batch($db, $batch, $config);
        sync_log_entry($db, [
            'direction' => 'push',
            'table_name' => null,
            'records_count' => ($stats['inserted'] + $stats['updated']),
            'conflicts_count' => $stats['conflicts'],
            'status' => $stats['errors'] > 0 ? 'partial' : 'success',
            'message' => 'Réception batch distant',
            'remote_node_id' => $input['node_id'] ?? null,
            'node_id' => $config['node_id'],
        ]);

        return [
            'success' => true,
            'stats' => $stats,
            'node_id' => $config['node_id'],
        ];
    }
}

if (!function_exists('sync_api_handle_file_push')) {
    function sync_api_handle_file_push(array $input, array $config) {
        $relative = $input['relative_path'] ?? '';
        $md5 = $input['md5'] ?? '';
        $content_b64 = $input['content_base64'] ?? '';

        if ($relative === '' || $md5 === '' || $content_b64 === '') {
            throw new InvalidArgumentException('Paramètres fichier manquants');
        }

        $relative = str_replace(['..', '\\'], ['', '/'], $relative);
        $root = dirname(__DIR__);
        $target = $root . '/' . ltrim($relative, '/');
        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = base64_decode($content_b64, true);
        if ($content === false || md5($content) !== $md5) {
            throw new InvalidArgumentException('Checksum fichier invalide');
        }

        file_put_contents($target, $content);

        return [
            'success' => true,
            'relative_path' => $relative,
            'size' => strlen($content),
        ];
    }
}

if (!function_exists('sync_api_verify_token')) {
    function sync_api_verify_token(array $config) {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $auth = '';
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $auth = $value;
                break;
            }
        }
        if ($auth === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
        }

        $expected = 'Bearer ' . ($config['remote_api_token'] ?? '');
        if ($expected === 'Bearer ' || !hash_equals($expected, $auth)) {
            return false;
        }
        return true;
    }
}

if (!function_exists('sync_get_recent_logs')) {
    function sync_get_recent_logs(PDO $db, $limit = 50) {
        $stmt = $db->prepare('SELECT * FROM sync_log ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
