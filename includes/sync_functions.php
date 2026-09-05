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

if (!function_exists('sync_table_primary_key_columns')) {
    function sync_table_primary_key_columns(PDO $db, $table) {
        $db_name = $db->query('SELECT DATABASE()')->fetchColumn();
        $stmt = $db->prepare(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_KEY = 'PRI'
             ORDER BY ORDINAL_POSITION ASC"
        );
        $stmt->execute([$db_name, $table]);
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return $cols ?: ['id'];
    }
}

if (!function_exists('sync_table_primary_key')) {
    function sync_table_primary_key(PDO $db, $table) {
        $cols = sync_table_primary_key_columns($db, $table);
        return $cols[0] ?? 'id';
    }
}

if (!function_exists('sync_find_local_by_primary_key')) {
    function sync_find_local_by_primary_key(PDO $db, $table, array $data) {
        $pk_cols = sync_table_primary_key_columns($db, $table);
        $where = [];
        $params = [];
        foreach ($pk_cols as $col) {
            if (!array_key_exists($col, $data)) {
                return null;
            }
            $where[] = "`$col` = ?";
            $params[] = $data[$col];
        }
        if (!$where) {
            return null;
        }
        $sql = 'SELECT * FROM `' . $table . '` WHERE ' . implode(' AND ', $where) . ' LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('sync_build_pk_where')) {
    function sync_build_pk_where(PDO $db, $table, array $row) {
        $pk_cols = sync_table_primary_key_columns($db, $table);
        $where = [];
        $params = [];
        foreach ($pk_cols as $col) {
            if (!array_key_exists($col, $row)) {
                return [null, []];
            }
            $where[] = "`$col` = ?";
            $params[] = $row[$col];
        }
        return [implode(' AND ', $where), $params];
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
        $stmt = $db->prepare("SELECT `$pk_col` FROM `$ref_table` WHERE sync_uuid = ? LIMIT 1");
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

if (!function_exists('sync_record_to_batch_item')) {
    function sync_record_to_batch_item(PDO $db, $table, array $row) {
        $sync_cols = ['sync_uuid', 'sync_updated_at', 'sync_deleted_at', 'sync_origin_node'];
        $meta = [];
        $data = $row;
        foreach ($sync_cols as $col) {
            if (array_key_exists($col, $data)) {
                $meta[$col] = $data[$col];
                unset($data[$col]);
            }
        }

        return [
            'table' => $table,
            'sync_uuid' => $meta['sync_uuid'] ?? null,
            'sync_updated_at' => $meta['sync_updated_at'] ?? null,
            'sync_deleted_at' => $meta['sync_deleted_at'] ?? null,
            'sync_origin_node' => $meta['sync_origin_node'] ?? null,
            'data' => $data,
            'fk_uuids' => sync_build_fk_uuid_map($db, $table, $data),
        ];
    }
}

if (!function_exists('sync_fetch_row_by_uuid')) {
    function sync_fetch_row_by_uuid(PDO $db, $table, $sync_uuid) {
        if ($sync_uuid === '' || !sync_registry_has_sync_columns($db, $table)) {
            return null;
        }
        $stmt = $db->prepare("SELECT * FROM `$table` WHERE sync_uuid = ? LIMIT 1");
        $stmt->execute([$sync_uuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('sync_expand_batch_fk_parents')) {
    function sync_expand_batch_fk_parents(PDO $db, array $batch) {
        $expanded = [];
        $seen = [];
        $queue = $batch;

        while ($queue) {
            $item = array_shift($queue);
            $uuid = (string) ($item['sync_uuid'] ?? '');
            if ($uuid !== '' && isset($seen[$uuid])) {
                continue;
            }
            if ($uuid !== '') {
                $seen[$uuid] = true;
            }
            $expanded[] = $item;

            foreach ($item['fk_uuids'] ?? [] as $fk) {
                if (!is_array($fk)) {
                    continue;
                }
                $parent_uuid = (string) ($fk['sync_uuid'] ?? '');
                $parent_table = (string) ($fk['ref_table'] ?? '');
                if ($parent_uuid === '' || $parent_table === '' || isset($seen[$parent_uuid])) {
                    continue;
                }
                $parent_row = sync_fetch_row_by_uuid($db, $parent_table, $parent_uuid);
                if (!$parent_row) {
                    continue;
                }
                $queue[] = sync_record_to_batch_item($db, $parent_table, $parent_row);
            }
        }

        return $expanded;
    }
}

if (!function_exists('sync_resolve_record_foreign_keys')) {
    function sync_resolve_record_foreign_keys(PDO $db, $table, array &$data, array $fk_uuids, &$stats) {
        foreach (sync_registry_foreign_keys($db, $table) as $fk) {
            $col = $fk['COLUMN_NAME'];
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $ref_table = $fk['REFERENCED_TABLE_NAME'];
            if (!sync_registry_has_sync_columns($db, $ref_table)) {
                continue;
            }

            $uuid = null;
            if (isset($fk_uuids[$col]) && is_array($fk_uuids[$col])) {
                $uuid = $fk_uuids[$col]['sync_uuid'] ?? null;
            }

            if ($uuid) {
                $resolved = sync_resolve_fk_value($db, $ref_table, $uuid);
                if ($resolved === null && $data[$col] !== null && $data[$col] !== '') {
                    $stats['fk_missing'] = ($stats['fk_missing'] ?? 0) + 1;
                    $stats['errors']++;
                    return false;
                }
                if ($resolved !== null) {
                    $data[$col] = $resolved;
                }
                continue;
            }

            if ($data[$col] !== null && $data[$col] !== '') {
                $stats['fk_missing'] = ($stats['fk_missing'] ?? 0) + 1;
                $stats['errors']++;
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('sync_batch_item_has_unresolved_fk')) {
    function sync_batch_item_has_unresolved_fk(PDO $db, array $item) {
        $table = $item['table'] ?? '';
        $data = is_array($item['data'] ?? null) ? $item['data'] : [];
        $fk_uuids = is_array($item['fk_uuids'] ?? null) ? $item['fk_uuids'] : [];

        foreach (sync_registry_foreign_keys($db, $table) as $fk) {
            $col = $fk['COLUMN_NAME'];
            if (!array_key_exists($col, $data) || $data[$col] === null || $data[$col] === '') {
                continue;
            }
            $ref_table = $fk['REFERENCED_TABLE_NAME'];
            if (!sync_registry_has_sync_columns($db, $ref_table)) {
                continue;
            }
            $uuid = $fk_uuids[$col]['sync_uuid'] ?? null;
            if (!$uuid) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('sync_filter_batch_unresolved_fk')) {
    function sync_filter_batch_unresolved_fk(PDO $db, array $batch) {
        $filtered = [];
        foreach ($batch as $item) {
            if (sync_batch_item_has_unresolved_fk($db, $item)) {
                continue;
            }
            $filtered[] = $item;
        }
        return $filtered;
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

        $pk_cols = sync_table_primary_key_columns($db, $table);
        $pk_col = $pk_cols[0];
        foreach ($pk_cols as $col) {
            unset($data[$col]);
        }
        if ($pk_col !== 'id') {
            unset($data['id']);
        }

        if (!sync_resolve_record_foreign_keys($db, $table, $data, $fk_uuids, $stats)) {
            return false;
        }

        $stmt = $db->prepare("SELECT `$pk_col`, sync_updated_at FROM `$table` WHERE sync_uuid = ? LIMIT 1");
        $stmt->execute([$sync_uuid]);
        $local = $stmt->fetch(PDO::FETCH_ASSOC);
        $merged_by_unique = false;

        if (!$local) {
            $existing_pk = sync_find_local_by_unique_keys($db, $table, $data, $pk_col);
            $existing_row = null;
            if ($existing_pk !== null) {
                $stmt = $db->prepare("SELECT * FROM `$table` WHERE `$pk_col` = ? LIMIT 1");
                $stmt->execute([$existing_pk]);
                $existing_row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            if (!$existing_row) {
                $original_data = is_array($item['data'] ?? null) ? $item['data'] : [];
                if ($original_data) {
                    $existing_row = sync_find_local_by_primary_key($db, $table, $original_data);
                }
            }
            if ($existing_row) {
                $local = $existing_row;
                $merged_by_unique = true;
            }
        }

        if ($local) {
            $local_pk = $local[$pk_col] ?? null;
            if (!$merged_by_unique) {
                $local_updated = $local['sync_updated_at'] ?? null;
                if ($local_updated && $remote_updated && strtotime($local_updated) === strtotime($remote_updated)) {
                    sync_store_id_map($db, $table, $sync_uuid, is_numeric($local_pk) ? (int) $local_pk : $local_pk);
                    $stats['skipped'] = ($stats['skipped'] ?? 0) + 1;
                    return true;
                }
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

            list($pk_where, $pk_params) = sync_build_pk_where($db, $table, $local);
            if ($pk_where === null) {
                $stats['errors']++;
                return false;
            }
            $values = array_merge($values, $pk_params);

            $db->exec('SET @sync_applying = 1');
            try {
                $sql = "UPDATE `$table` SET " . implode(', ', $sets) . ' WHERE ' . $pk_where;
                $upd = $db->prepare($sql);
                $upd->execute($values);
            } catch (PDOException $e) {
                $db->exec('SET @sync_applying = 0');
                $stats['errors']++;
                return false;
            }
            $db->exec('SET @sync_applying = 0');

            if (count($pk_cols) === 1 && is_numeric($local_pk)) {
                sync_store_id_map($db, $table, $sync_uuid, (int) $local_pk);
            }
            if ($merged_by_unique) {
                $stats['merged'] = ($stats['merged'] ?? 0) + 1;
            } else {
                $stats['updated']++;
            }
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
        try {
            $sql = 'INSERT INTO `' . $table . '` (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $ins = $db->prepare($sql);
            $ins->execute($values);
            $new_id = (int) $db->lastInsertId();
        } catch (PDOException $e) {
            $db->exec('SET @sync_applying = 0');
            if (stripos($e->getMessage(), 'Duplicate') !== false) {
                $original_data = is_array($item['data'] ?? null) ? $item['data'] : $insert_data;
                $existing_row = sync_find_local_by_primary_key($db, $table, $original_data);
                if (!$existing_row) {
                    $existing_pk = sync_find_local_by_unique_keys($db, $table, $insert_data, $pk_col);
                    if ($existing_pk !== null) {
                        $stmt = $db->prepare("SELECT * FROM `$table` WHERE `$pk_col` = ? LIMIT 1");
                        $stmt->execute([$existing_pk]);
                        $existing_row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                    }
                }
                if ($existing_row) {
                    list($pk_where, $pk_params) = sync_build_pk_where($db, $table, $existing_row);
                    if ($pk_where !== null) {
                        $upd_data = $insert_data;
                        $sets = [];
                        $upd_values = [];
                        foreach ($upd_data as $col => $val) {
                            if (!sync_table_has_column($db, $table, $col)) {
                                continue;
                            }
                            $sets[] = "`$col` = ?";
                            $upd_values[] = $val;
                        }
                        $upd_values = array_merge($upd_values, $pk_params);
                        $db->exec('SET @sync_applying = 1');
                        $upd = $db->prepare('UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE ' . $pk_where);
                        $upd->execute($upd_values);
                        $db->exec('SET @sync_applying = 0');
                        if (count($pk_cols) === 1 && isset($existing_row[$pk_col]) && is_numeric($existing_row[$pk_col])) {
                            sync_store_id_map($db, $table, $sync_uuid, (int) $existing_row[$pk_col]);
                        }
                        $stats['merged'] = ($stats['merged'] ?? 0) + 1;
                        return true;
                    }
                }
            }
            $stats['errors']++;
            return false;
        }
        $db->exec('SET @sync_applying = 0');

        sync_store_id_map($db, $table, $sync_uuid, $new_id);
        $stats['inserted']++;
        return true;
    }
}

if (!function_exists('sync_apply_batch')) {
    function sync_apply_batch(PDO $db, array $batch, array $config) {
        $stats = ['inserted' => 0, 'updated' => 0, 'merged' => 0, 'skipped' => 0, 'conflicts' => 0, 'errors' => 0];
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

if (!function_exists('sync_build_api_url')) {
    function sync_build_api_url($action, ?array $config = null) {
        $config = $config ?: sync_load_config();
        $base = rtrim($config['remote_url'] ?? '', '/');
        $path = $config['remote_api_path'] ?? '/sync/api.php';
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }
        return $base . $path . '?action=' . urlencode($action);
    }
}

if (!function_exists('sync_remote_request')) {
    function sync_remote_request($action, array $payload, ?array $config = null) {
        $config = $config ?: sync_load_config();
        $url = sync_build_api_url($action, $config);

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
        if (!is_string($ca_bundle) || $ca_bundle === '' || !is_file($ca_bundle)) {
            $ca_bundle = dirname(__DIR__) . '/config/cacert.pem';
        }
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
            if ($http_code === 404) {
                $message .= ' — URL appelée : ' . $url
                    . ' (vérifiez remote_url et remote_api_path ; sync/api.php doit être dans la racine web)';
            }
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
            $total += ($stats['inserted'] + $stats['updated'] + ($stats['merged'] ?? 0));
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
    function sync_pull(PDO $db, ?array $config = null, $dry_run = false) {
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
        $skipped = 0;
        $max_seen = $cursor;

        do {
            $raw_batch = sync_get_pending_records($db, $table, $cursor, $limit, true);
            if (!$raw_batch) {
                break;
            }

            foreach ($raw_batch as $item) {
                $ts = $item['sync_updated_at'] ?? null;
                if ($ts && strtotime($ts) > strtotime($max_seen)) {
                    $max_seen = $ts;
                }
            }

            $valid = [];
            foreach ($raw_batch as $item) {
                if (sync_batch_item_has_unresolved_fk($db, $item)) {
                    $skipped++;
                    continue;
                }
                $valid[] = $item;
            }

            $batch = $valid ? sync_expand_batch_fk_parents($db, $valid) : [];
            $batch = sync_filter_batch_unresolved_fk($db, $batch);

            if (!$batch) {
                if (count($raw_batch) < $limit) {
                    break;
                }
                $cursor = $max_seen;
                continue;
            }

            $response = sync_remote_request('push', [
                'node_id' => $config['node_id'],
                'batch' => $batch,
            ], $config);

            $stats = $response['stats'] ?? [];
            $total += (int) (($stats['inserted'] ?? 0) + ($stats['updated'] ?? 0) + ($stats['merged'] ?? 0));
            $conflicts += (int) ($stats['conflicts'] ?? 0);
            $skipped += (int) ($stats['skipped'] ?? 0);

            if (count($raw_batch) < $limit) {
                break;
            }
            $cursor = $max_seen;
        } while (true);

        return ['records' => $total, 'conflicts' => $conflicts, 'skipped' => $skipped, 'max_seen' => $max_seen];
    }
}

if (!function_exists('sync_push')) {
    function sync_push(PDO $db, ?array $config = null, $dry_run = false) {
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
        $total_skipped = 0;
        $max_seen = $since;

        foreach ($tables as $table) {
            $result = sync_push_table($db, $table, $config, $since);
            $total_records += $result['records'];
            $total_conflicts += $result['conflicts'];
            $total_skipped += (int) ($result['skipped'] ?? 0);
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
            'skipped' => $total_skipped,
            'since' => $since,
            'max_seen' => $max_seen,
        ];
    }
}

if (!function_exists('sync_get_direction')) {
    function sync_get_direction(?array $config = null) {
        $config = $config ?: sync_load_config();
        $dir = strtolower(trim((string) ($config['sync_direction'] ?? 'push_only')));
        if (!in_array($dir, ['push_only', 'pull_only', 'bidirectional'], true)) {
            return 'push_only';
        }
        return $dir;
    }
}

if (!function_exists('sync_direction_allows_pull')) {
    function sync_direction_allows_pull(?array $config = null) {
        $dir = sync_get_direction($config);
        return $dir === 'pull_only' || $dir === 'bidirectional';
    }
}

if (!function_exists('sync_direction_allows_push')) {
    function sync_direction_allows_push(?array $config = null) {
        $dir = sync_get_direction($config);
        return $dir === 'push_only' || $dir === 'bidirectional';
    }
}

if (!function_exists('sync_direction_label')) {
    function sync_direction_label(?array $config = null) {
        switch (sync_get_direction($config)) {
            case 'pull_only':
                return 'VPS → local uniquement';
            case 'bidirectional':
                return 'Bidirectionnelle (pull + push)';
            case 'push_only':
            default:
                return 'Local → VPS uniquement';
        }
    }
}

if (!function_exists('sync_run')) {
    function sync_run(PDO $db, ?array $config = null, $dry_run = false) {
        $config = $config ?: sync_load_config();
        $result = [];

        if (sync_direction_allows_pull($config)) {
            $result['pull'] = sync_pull($db, $config, $dry_run);
        }

        if (sync_direction_allows_push($config)) {
            if (!empty($config['sync_include_files']) && sync_get_direction($config) === 'push_only') {
                $result = sync_local_to_vps($db, $config, $dry_run);
                return $result;
            }
            $result['push'] = sync_push($db, $config, $dry_run);
            if (!empty($config['sync_include_files'])) {
                $result['files'] = sync_files_push($db, $config, $dry_run);
            }
        }

        if (!$result) {
            return ['message' => 'Aucune direction sync active dans config/sync.php'];
        }

        return $result;
    }
}

if (!function_exists('sync_verify_remote_db')) {
    function sync_verify_remote_db(PDO $local_db, ?array $config = null) {
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

if (!function_exists('sync_file_normalize_relative_path')) {
    function sync_file_normalize_relative_path($path) {
        $path = str_replace('\\', '/', trim((string) $path));
        $path = ltrim($path, '/');
        if ($path === '') {
            return '';
        }
        if (stripos($path, 'upload/') === 0) {
            return $path;
        }
        return 'upload/' . ltrim($path, '/');
    }
}

if (!function_exists('sync_file_priority_prefixes')) {
    function sync_file_priority_prefixes(?array $config = null) {
        $config = $config ?: sync_load_config();
        $prefixes = $config['upload_dirs_priority'] ?? [
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
        ];
        $normalized = [];
        foreach ($prefixes as $prefix) {
            $prefix = rtrim(str_replace('\\', '/', $prefix), '/');
            if ($prefix !== '') {
                $normalized[] = $prefix . '/';
            }
        }
        return $normalized;
    }
}

if (!function_exists('sync_file_priority_score')) {
    function sync_file_priority_score($relative, ?array $config = null) {
        $relative = sync_file_normalize_relative_path($relative);
        foreach (sync_file_priority_prefixes($config) as $index => $prefix) {
            if (strpos($relative, $prefix) === 0) {
                return $index;
            }
        }
        return 9999;
    }
}

if (!function_exists('sync_expand_image_variant_paths')) {
    function sync_expand_image_variant_paths($relative) {
        $relative = sync_file_normalize_relative_path($relative);
        if ($relative === '') {
            return [];
        }
        $paths = [$relative];
        $info = pathinfo($relative);
        $dir = $info['dirname'] ?? '';
        $filename = $info['filename'] ?? '';
        $ext = $info['extension'] ?? '';
        if ($dir === '' || $filename === '' || $ext === '') {
            return $paths;
        }
        foreach (['_md', '_sm'] as $suffix) {
            $paths[] = $dir . '/' . $filename . $suffix . '.' . $ext;
        }
        return array_values(array_unique($paths));
    }
}

if (!function_exists('sync_collect_db_media_paths')) {
    function sync_collect_db_media_paths(PDO $db, $since = null) {
        $paths = [];
        $add = function ($path) use (&$paths) {
            $normalized = sync_file_normalize_relative_path($path);
            if ($normalized === '') {
                return;
            }
            foreach (sync_expand_image_variant_paths($normalized) as $variant) {
                $paths[$variant] = true;
            }
        };

        $queries = [
            "SELECT image_principale, images, image_etiquette_fpl FROM produits WHERE sync_updated_at IS NOT NULL",
            "SELECT image FROM categories WHERE sync_updated_at IS NOT NULL",
            "SELECT image FROM slider WHERE sync_updated_at IS NOT NULL",
            "SELECT image FROM logos WHERE sync_updated_at IS NOT NULL",
            "SELECT fichier_video FROM videos WHERE sync_updated_at IS NOT NULL AND fichier_video IS NOT NULL AND fichier_video != ''",
            "SELECT photo_chemin, qr_chemin FROM employes WHERE sync_updated_at IS NOT NULL",
        ];

        foreach ($queries as $sql) {
            if ($since) {
                $sql .= ' AND sync_updated_at > ' . $db->quote($since);
            }
            try {
                $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException $e) {
                continue;
            }
            foreach ($rows as $row) {
                foreach ($row as $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }
                    if ($value[0] === '{' || $value[0] === '[') {
                        $decoded = json_decode($value, true);
                        if (is_array($decoded)) {
                            foreach ($decoded as $item) {
                                if (is_string($item) && $item !== '') {
                                    $add($item);
                                }
                            }
                            continue;
                        }
                    }
                    if (strpos($value, ',') !== false) {
                        foreach (explode(',', $value) as $part) {
                            $part = trim($part);
                            if ($part !== '') {
                                $add($part);
                            }
                        }
                        continue;
                    }
                    $add($value);
                }
            }
        }

        return array_keys($paths);
    }
}

if (!function_exists('sync_files_sort_by_priority')) {
    function sync_files_sort_by_priority(array $files, ?array $config = null) {
        usort($files, function ($a, $b) use ($config) {
            $pa = sync_file_priority_score($a['relative_path'] ?? '', $config);
            $pb = sync_file_priority_score($b['relative_path'] ?? '', $config);
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }
            $ma = $a['mtime'] ?? '';
            $mb = $b['mtime'] ?? '';
            if ($ma !== $mb) {
                return strcmp($mb, $ma);
            }
            return strcmp($a['relative_path'] ?? '', $b['relative_path'] ?? '');
        });
        return $files;
    }
}

if (!function_exists('sync_file_allowed_extension')) {
    function sync_file_allowed_extension($relative_path, ?array $config = null) {
        $config = $config ?: sync_load_config();
        $exts = $config['upload_file_extensions'] ?? [];
        if (!$exts) {
            return true;
        }
        $ext = strtolower(pathinfo($relative_path, PATHINFO_EXTENSION));
        return in_array($ext, array_map('strtolower', $exts), true);
    }
}

if (!function_exists('sync_file_get_synced_md5')) {
    function sync_file_get_synced_md5(PDO $db, $relative_path) {
        $stmt = $db->prepare(
            "SELECT file_md5 FROM sync_file_queue WHERE relative_path = ? AND status = 'done' LIMIT 1"
        );
        $stmt->execute([$relative_path]);
        $md5 = $stmt->fetchColumn();
        return $md5 !== false ? (string) $md5 : null;
    }
}

if (!function_exists('sync_file_mark_synced')) {
    function sync_file_mark_synced(PDO $db, $relative_path, $md5, $size = 0) {
        $stmt = $db->prepare(
            "INSERT INTO sync_file_queue (relative_path, file_md5, file_size, direction, status, sync_updated_at)
             VALUES (?, ?, ?, 'push', 'done', NOW())
             ON DUPLICATE KEY UPDATE file_md5 = VALUES(file_md5), file_size = VALUES(file_size),
             status = 'done', last_error = NULL, sync_updated_at = NOW()"
        );
        $stmt->execute([$relative_path, $md5, (int) $size]);
    }
}

if (!function_exists('sync_files_scan')) {
    function sync_files_scan(?array $config = null, array $options = []) {
        $config = $config ?: sync_load_config();
        $root = dirname(__DIR__);
        $dirs = $config['upload_dirs'] ?? [$config['upload_dir'] ?? 'upload'];
        if (!is_array($dirs)) {
            $dirs = [$dirs];
        }

        $only_paths = [];
        if (!empty($options['db_referenced_only'])) {
            global $db;
            if ($db instanceof PDO) {
                $since = $options['since'] ?? null;
                foreach (sync_collect_db_media_paths($db, $since) as $rel) {
                    $only_paths[$rel] = true;
                }
            }
        }

        $files = [];
        $seen = [];
        foreach ($dirs as $dir) {
            $upload_dir = $root . '/' . ltrim($dir, '/');
            if (!is_dir($upload_dir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($upload_dir, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $full = $file->getPathname();
                $relative = ltrim(str_replace('\\', '/', substr($full, strlen($root . '/'))), '/');
                if (isset($seen[$relative])) {
                    continue;
                }
                if (!sync_file_allowed_extension($relative, $config)) {
                    continue;
                }
                if ($only_paths && !isset($only_paths[$relative])) {
                    continue;
                }
                $seen[$relative] = true;
                $files[] = [
                    'relative_path' => $relative,
                    'md5' => md5_file($full),
                    'size' => filesize($full),
                    'mtime' => date('Y-m-d H:i:s', filemtime($full)),
                ];
            }
        }

        return sync_files_sort_by_priority($files, $config);
    }
}

if (!function_exists('sync_files_push_batch_remote')) {
    function sync_files_push_batch_remote(array $batch_files, ?array $config = null) {
        $config = $config ?: sync_load_config();
        $payload_files = [];
        foreach ($batch_files as $file) {
            $relative = $file['relative_path'];
            $full = dirname(__DIR__) . '/' . $relative;
            if (!is_file($full)) {
                continue;
            }
            $content = file_get_contents($full);
            if ($content === false) {
                continue;
            }
            $payload_files[] = [
                'relative_path' => $relative,
                'md5' => $file['md5'],
                'content_base64' => base64_encode($content),
            ];
        }
        if (!$payload_files) {
            return ['pushed' => 0, 'skipped' => 0, 'errors' => 0, 'results' => []];
        }

        if (count($payload_files) === 1) {
            $response = sync_remote_request('file_push', $payload_files[0], $config);
            $pushed = empty($response['skipped']) ? 1 : 0;
            $skipped = !empty($response['skipped']) ? 1 : 0;
            return [
                'pushed' => $pushed,
                'skipped' => $skipped,
                'errors' => 0,
                'results' => [$response],
            ];
        }

        try {
            $response = sync_remote_request('file_push_batch', ['files' => $payload_files], $config);
        } catch (Throwable $e) {
            $pushed = 0;
            $skipped = 0;
            $errors = 0;
            $results = [];
            foreach ($payload_files as $one) {
                try {
                    $response = sync_remote_request('file_push', $one, $config);
                    $results[] = $response;
                    if (!empty($response['skipped'])) {
                        $skipped++;
                    } else {
                        $pushed++;
                    }
                } catch (Throwable $inner) {
                    $errors++;
                    $results[] = [
                        'success' => false,
                        'relative_path' => $one['relative_path'] ?? '',
                        'error' => $inner->getMessage(),
                    ];
                }
            }
            return [
                'pushed' => $pushed,
                'skipped' => $skipped,
                'errors' => $errors,
                'results' => $results,
            ];
        }

        $results = $response['results'] ?? [];
        $pushed = (int) ($response['files_pushed'] ?? 0);
        $skipped = (int) ($response['files_skipped'] ?? 0);
        $errors = (int) ($response['files_errors'] ?? 0);
        return [
            'pushed' => $pushed,
            'skipped' => $skipped,
            'errors' => $errors,
            'results' => $results,
        ];
    }
}

if (!function_exists('sync_files_push')) {
    function sync_files_push(PDO $db, ?array $config = null, $dry_run = false, array $options = []) {
        $config = $config ?: sync_load_config();
        sync_ensure_infrastructure($db);
        ini_set('max_execution_time', '0');

        $scan_options = [];
        if (!empty($options['db_referenced_only'])) {
            $scan_options['db_referenced_only'] = true;
            $scan_options['since'] = $options['since'] ?? sync_get_state($db, 'last_push_since', '1970-01-01 00:00:00');
        }

        $files = sync_files_scan($config, $scan_options);
        $pushed = 0;
        $skipped = 0;
        $errors = 0;
        $processed = 0;
        $max_per_run = (int) ($options['max_per_run'] ?? ($config['files_max_per_run'] ?? 0));
        $batch_count = max(1, (int) ($config['files_batch_count'] ?? 8));
        $max_batch_bytes = max(512000, (int) ($config['files_batch_max_bytes'] ?? (6 * 1024 * 1024)));
        $show_progress = !empty($options['progress']) || (PHP_SAPI === 'cli' && !empty($options['cli_progress']));

        $pending = [];
        foreach ($files as $file) {
            $relative = $file['relative_path'];
            $md5 = $file['md5'];
            $synced_md5 = sync_file_get_synced_md5($db, $relative);
            if ($synced_md5 === $md5) {
                $skipped++;
                continue;
            }
            $pending[] = $file;
        }

        $batch = [];
        $batch_bytes = 0;
        $flush_batch = function () use (&$batch, &$batch_bytes, &$pushed, &$skipped, &$errors, &$processed, $db, $config, $dry_run, $show_progress) {
            if (!$batch) {
                return;
            }
            if ($dry_run) {
                $pushed += count($batch);
                $processed += count($batch);
                $batch = [];
                $batch_bytes = 0;
                return;
            }

            try {
                $result = sync_files_push_batch_remote($batch, $config);
                $results = $result['results'] ?? [];
                if ($results) {
                    foreach ($results as $i => $item) {
                        if (!is_array($item) || empty($item['success'])) {
                            $errors++;
                            continue;
                        }
                        $rel = $item['relative_path'] ?? ($batch[$i]['relative_path'] ?? '');
                        $md5 = $batch[$i]['md5'] ?? '';
                        $size = (int) ($batch[$i]['size'] ?? 0);
                        if ($rel !== '' && $md5 !== '') {
                            sync_file_mark_synced($db, $rel, $md5, $size);
                        }
                        if (!empty($item['skipped'])) {
                            $skipped++;
                        } else {
                            $pushed++;
                        }
                        $processed++;
                        if ($show_progress && $rel !== '') {
                            echo '[fichier] ' . ($item['skipped'] ?? false ? 'skip' : 'push') . ' ' . $rel . "\n";
                        }
                    }
                } else {
                    $pushed += (int) ($result['pushed'] ?? 0);
                    $skipped += (int) ($result['skipped'] ?? 0);
                    $errors += (int) ($result['errors'] ?? 0);
                    $processed += count($batch);
                    foreach ($batch as $file) {
                        if (($result['errors'] ?? 0) === 0) {
                            sync_file_mark_synced($db, $file['relative_path'], $file['md5'], (int) $file['size']);
                        }
                    }
                }
            } catch (Throwable $e) {
                $errors += count($batch);
                if ($show_progress) {
                    echo '[fichier] ERREUR batch: ' . $e->getMessage() . "\n";
                }
            }

            $batch = [];
            $batch_bytes = 0;
        };

        foreach ($pending as $file) {
            if ($max_per_run > 0 && $processed >= $max_per_run) {
                break;
            }

            if ($dry_run) {
                $pushed++;
                continue;
            }

            $size = (int) ($file['size'] ?? 0);
            if ($batch && ($batch_bytes + $size > $max_batch_bytes || count($batch) >= $batch_count)) {
                $flush_batch();
            }
            $batch[] = $file;
            $batch_bytes += $size;

            if (count($batch) >= $batch_count) {
                $flush_batch();
            }
        }
        $flush_batch();

        if (!$dry_run && ($pushed > 0 || $skipped > 0)) {
            sync_log_entry($db, [
                'direction' => 'files',
                'records_count' => $pushed,
                'conflicts_count' => 0,
                'status' => $errors > 0 ? 'partial' : 'success',
                'message' => "Fichiers: $pushed envoyé(s), $skipped déjà à jour, $errors erreur(s)",
                'node_id' => $config['node_id'],
            ]);
        }

        return [
            'files_pushed' => $pushed,
            'files_skipped' => $skipped,
            'files_errors' => $errors,
            'files_scanned' => count($files),
            'files_pending' => count($pending),
        ];
    }
}

if (!function_exists('sync_local_to_vps')) {
    function sync_local_to_vps(PDO $db, ?array $config = null, $dry_run = false, array $options = []) {
        $config = $config ?: sync_load_config();
        $result = [];

        if (!sync_direction_allows_push($config)) {
            throw new RuntimeException('Push désactivé dans config/sync.php (sync_direction).');
        }

        $files_only = !empty($options['files_only']);

        if (!$files_only) {
            /* CHAQUE nouvelle ligne doit avoir un sync_uuid pour partir au VPS.
             * Le code de création des PRODUITS en pose un ; celui des NŒUDS
             * D'ENTREPÔT (écran Structure : barres, étagères, box…) n'en posait
             * pas — d'où des ajouts qui ne remontaient jamais. On pose ici les
             * uuid manquants sur TOUTES les tables synchronisées avant le push :
             * désormais tout ajout/modification sur foutasvr part au VPS. */
            if (!$dry_run) {
                try {
                    sync_assign_missing_uuids($db, $config);
                } catch (Throwable $e) {
                    // non bloquant : le push continue avec ce qui a déjà un uuid
                }
            }
            try {
                $result['push'] = sync_push($db, $config, $dry_run);
            } catch (Throwable $e) {
                $result['push'] = [
                    'error' => $e->getMessage(),
                    'records' => 0,
                    'conflicts' => 0,
                    'skipped' => 0,
                ];
            }
        }

        if (!empty($config['sync_include_files'])) {
            $file_options = [
                'cli_progress' => true,
                'db_referenced_only' => !empty($options['files_priority_db']),
            ];
            if (!empty($options['files_max_per_run'])) {
                $file_options['max_per_run'] = (int) $options['files_max_per_run'];
            }
            $result['files'] = sync_files_push($db, $config, $dry_run, $file_options);
        }

        return $result;
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
            'records_count' => ($stats['inserted'] + $stats['updated'] + ($stats['merged'] ?? 0)),
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

        if (is_file($target) && md5_file($target) === $md5) {
            return [
                'success' => true,
                'skipped' => true,
                'relative_path' => $relative,
                'size' => filesize($target),
                'message' => 'Fichier déjà présent avec le même contenu',
            ];
        }

        file_put_contents($target, $content);

        return [
            'success' => true,
            'skipped' => false,
            'relative_path' => $relative,
            'size' => strlen($content),
        ];
    }
}

if (!function_exists('sync_api_handle_file_push_batch')) {
    function sync_api_handle_file_push_batch(array $input, array $config) {
        $files = $input['files'] ?? [];
        if (!is_array($files)) {
            throw new InvalidArgumentException('files invalide');
        }

        $results = [];
        $pushed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($files as $file) {
            if (!is_array($file)) {
                $errors++;
                continue;
            }
            try {
                $result = sync_api_handle_file_push($file, $config);
                $results[] = $result;
                if (!empty($result['skipped'])) {
                    $skipped++;
                } else {
                    $pushed++;
                }
            } catch (Throwable $e) {
                $errors++;
                $results[] = [
                    'success' => false,
                    'relative_path' => $file['relative_path'] ?? '',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => true,
            'files_pushed' => $pushed,
            'files_skipped' => $skipped,
            'files_errors' => $errors,
            'results' => $results,
        ];
    }
}

if (!function_exists('sync_api_get_authorization_header')) {
    function sync_api_get_authorization_header() {
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $key => $value) {
                if (strtolower((string) $key) === 'authorization') {
                    return trim((string) $value);
                }
            }
        }

        $server_keys = [
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION',
            'Authorization',
            'HTTP_X_SYNC_TOKEN',
        ];
        foreach ($server_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $value = trim((string) $_SERVER[$key]);
                if ($key === 'HTTP_X_SYNC_TOKEN' && stripos($value, 'Bearer ') !== 0) {
                    return 'Bearer ' . $value;
                }
                return $value;
            }
        }

        if (!empty($_GET['token'])) {
            return 'Bearer ' . trim((string) $_GET['token']);
        }

        return '';
    }
}

if (!function_exists('sync_api_verify_token')) {
    function sync_api_verify_token(array $config) {
        $auth = sync_api_get_authorization_header();
        $token = trim((string) ($config['remote_api_token'] ?? ''));
        if ($token === '') {
            return false;
        }

        $expected = 'Bearer ' . $token;
        if ($auth === '') {
            return false;
        }

        if (hash_equals($expected, $auth)) {
            return true;
        }

        // Accepte aussi le token seul sans préfixe Bearer
        if (hash_equals($token, preg_replace('/^Bearer\s+/i', '', $auth))) {
            return true;
        }

        return false;
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
