<?php
/**
 * Registre des tables pour la synchronisation (ordre de dépendance FK).
 * Les tables absentes de la base locale sont ignorées automatiquement.
 */

require_once __DIR__ . '/sync_fk_static.php';

if (!function_exists('sync_registry_priority_tables')) {
    function sync_registry_priority_tables() {
        return [
            // Niveau 1 — tables racines
            'admin',
            'users',
            'categories',
            'zones_livraison',
            'fournisseurs',
            'marques',
            'sous_categories',
            'categories_depenses',
            'logos',
            'slider',
            'section4_config',
            'trending_config',
            'videos',
            'contacts',
            'clients_b2b',
            'employes',
            'stock_alertes_regles',
            'bulletin_paie_parametres',
            'entrepot_etage',
            'entrepot_emplacement_config',
            'entrepot_hierarchie_niveau',
            // Niveau 2
            'produits',
            'produits_variantes',
            'employes_matricules',
            'stock_alertes_regles_categories',
            'stock_alertes_regles_sous_categories',
            'entrepot_rayon',
            'entrepot_allee',
            'entrepot_zone',
            'entrepot_barre',
            'entrepot_position',
            'entrepot_barre_code_seq',
            'entrepot_emplacement_etage',
            'entrepot_champ_element',
            'entrepot_structure_champ',
            'entrepot_etagere',
            'entrepot_hierarchie_noeud',
            'produit_formulaire_champ_role',
            // Niveau 3
            'commandes',
            'devis',
            'caisse_ventes',
            'stock_mouvements',
            'depenses',
            'commandes_personnalisees',
            'factures',
            'produits_visites',
            'favoris',
            'employe_absences',
            'employe_autorisations_absence',
            'employe_conges',
            'employe_documents',
            'employe_prets',
            'employe_pret_remboursements',
            'employe_sanctions',
            'entrepot_structure_champ_archive',
            // Niveau 4 — tables enfants
            'commande_produits',
            'devis_produits',
            'factures_devis',
            'bons_livraison',
            'bl_lignes',
            'factures_mensuelles',
            'facture_mensuelle_bl',
            'caisse_vente_lignes',
            'employe_absence_justificatifs',
        ];
    }
}

if (!function_exists('sync_registry_get_excluded_tables')) {
    function sync_registry_get_excluded_tables($config = null) {
        $defaults = [
            'sync_log',
            'sync_state',
            'sync_id_map',
            'sync_file_queue',
            'user_password_reset',
            'admin_password_reset',
            'fcm_tokens',
            'panier',
        ];

        if (is_array($config) && !empty($config['excluded_tables'])) {
            return array_values(array_unique(array_merge($defaults, $config['excluded_tables'])));
        }

        return $defaults;
    }
}

if (!function_exists('sync_registry_discover_tables')) {
    function sync_registry_discover_tables(PDO $db, $config = null) {
        $db_name = $db->query('SELECT DATABASE()')->fetchColumn();
        if (!$db_name) {
            return [];
        }

        $excluded = sync_registry_get_excluded_tables($config);
        $placeholders = implode(',', array_fill(0, count($excluded), '?'));

        $sql = "SELECT TABLE_NAME FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'";
        if ($excluded) {
            $sql .= " AND TABLE_NAME NOT IN ($placeholders)";
        }
        $sql .= ' ORDER BY TABLE_NAME';

        $stmt = $db->prepare($sql);
        $params = array_merge([$db_name], $excluded);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}

if (!function_exists('sync_registry_foreign_keys')) {
    function sync_registry_foreign_keys(PDO $db, $table) {
        $db_name = $db->query('SELECT DATABASE()')->fetchColumn();
        $stmt = $db->prepare(
            "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL"
        );
        $stmt->execute([$db_name, $table]);
        $fks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $static = sync_registry_static_foreign_keys($table);
        if (!$fks) {
            return $static;
        }
        if (!$static) {
            return $fks;
        }

        $by_col = [];
        foreach (array_merge($static, $fks) as $fk) {
            $by_col[$fk['COLUMN_NAME']] = $fk;
        }

        return array_values($by_col);
    }
}

if (!function_exists('sync_registry_sort_tables')) {
    function sync_registry_sort_tables(PDO $db, $config = null) {
        $tables = sync_registry_discover_tables($db, $config);
        if (!$tables) {
            return [];
        }

        $priority = sync_registry_priority_tables();
        $priority_index = [];
        foreach ($priority as $i => $name) {
            $priority_index[$name] = $i;
        }

        $deps = [];
        foreach ($tables as $table) {
            $deps[$table] = [];
            foreach (sync_registry_foreign_keys($db, $table) as $fk) {
                $parent = $fk['REFERENCED_TABLE_NAME'];
                if (in_array($parent, $tables, true) && $parent !== $table) {
                    $deps[$table][] = $parent;
                }
            }
            $deps[$table] = array_values(array_unique($deps[$table]));
        }

        $sorted = [];
        $visited = [];
        $visiting = [];

        $visit = function ($table) use (&$visit, &$sorted, &$visited, &$visiting, $deps) {
            if (isset($visited[$table])) {
                return;
            }
            if (isset($visiting[$table])) {
                $sorted[] = $table;
                $visited[$table] = true;
                return;
            }
            $visiting[$table] = true;
            foreach ($deps[$table] ?? [] as $parent) {
                $visit($parent);
            }
            unset($visiting[$table]);
            $visited[$table] = true;
            $sorted[] = $table;
        };

        usort($tables, function ($a, $b) use ($priority_index) {
            $ia = $priority_index[$a] ?? 9999;
            $ib = $priority_index[$b] ?? 9999;
            if ($ia === $ib) {
                return strcmp($a, $b);
            }
            return $ia <=> $ib;
        });

        foreach ($tables as $table) {
            $visit($table);
        }

        return array_values(array_unique($sorted));
    }
}

if (!function_exists('sync_registry_table_columns')) {
    function sync_registry_table_columns(PDO $db, $table) {
        $db_name = $db->query('SELECT DATABASE()')->fetchColumn();
        $stmt = $db->prepare(
            "SELECT COLUMN_NAME, EXTRA, COLUMN_KEY
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION"
        );
        $stmt->execute([$db_name, $table]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sync_registry_unique_indexes')) {
    function sync_registry_unique_indexes(PDO $db, $table) {
        $db_name = $db->query('SELECT DATABASE()')->fetchColumn();
        $stmt = $db->prepare(
            "SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
               AND NON_UNIQUE = 0 AND INDEX_NAME <> 'PRIMARY'
             ORDER BY INDEX_NAME, SEQ_IN_INDEX"
        );
        $stmt->execute([$db_name, $table]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $indexes = [];
        foreach ($rows as $row) {
            $name = $row['INDEX_NAME'];
            if (!isset($indexes[$name])) {
                $indexes[$name] = [];
            }
            $indexes[$name][] = $row['COLUMN_NAME'];
        }

        $result = [];
        foreach ($indexes as $columns) {
            $columns = array_values(array_filter($columns, function ($col) {
                return !in_array($col, ['sync_uuid'], true);
            }));
            if ($columns) {
                $result[] = $columns;
            }
        }

        usort($result, function ($a, $b) {
            return count($a) <=> count($b);
        });

        return $result;
    }
}

if (!function_exists('sync_find_local_by_unique_keys')) {
    function sync_find_local_by_unique_keys(PDO $db, $table, array $data, $pk_col) {
        foreach (sync_registry_unique_indexes($db, $table) as $columns) {
            $where = [];
            $params = [];
            $usable = true;
            foreach ($columns as $col) {
                if ($col === $pk_col) {
                    continue;
                }
                if (!array_key_exists($col, $data)) {
                    $usable = false;
                    break;
                }
                $val = $data[$col];
                if ($val === null || $val === '') {
                    $usable = false;
                    break;
                }
                $where[] = "`$col` = ?";
                $params[] = $val;
            }
            if (!$usable || !$where) {
                continue;
            }

            $sql = "SELECT `$pk_col` FROM `$table` WHERE " . implode(' AND ', $where) . ' LIMIT 1';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $pk = $stmt->fetchColumn();
            if ($pk !== false) {
                return $pk;
            }
        }

        return null;
    }
}

if (!function_exists('sync_registry_has_sync_columns')) {
    function sync_registry_has_sync_columns(PDO $db, $table) {
        $columns = sync_registry_table_columns($db, $table);
        $names = array_column($columns, 'COLUMN_NAME');
        $required = ['sync_uuid', 'sync_updated_at', 'sync_deleted_at', 'sync_origin_node'];
        foreach ($required as $col) {
            if (!in_array($col, $names, true)) {
                return false;
            }
        }
        return true;
    }
}
