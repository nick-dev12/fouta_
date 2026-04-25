<?php
/**
 * Statistiques d'activité liées à un compte admin (traçabilité métier)
 */
require_once __DIR__ . '/../conn/conn.php';

/**
 * @param string $table
 * @param string $column
 */
function admin_activite_column_exists($table, $column) {
    global $db;
    static $cols_cache = [];
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $c = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($t === '' || $c === '') {
        return false;
    }
    if (!isset($cols_cache[$t])) {
        $cols_cache[$t] = [];
        try {
            $stmt = $db->query('SHOW COLUMNS FROM `' . $t . '`');
            if ($stmt) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (!empty($row['Field'])) {
                        $cols_cache[$t][$row['Field']] = true;
                    }
                }
            }
        } catch (PDOException $e) {
            $cols_cache[$t] = [];
        }
    }
    return !empty($cols_cache[$t][$c]);
}

/**
 * Compteurs liés à un administrateur (IDs créateur / traçabilité).
 *
 * @return array{
 *   nb_commandes_traitees:int,
 *   nb_commandes_creees:int,
 *   nb_devis:int,
 *   nb_factures_devis:int,
 *   nb_factures_mensuelles:int,
 *   nb_bl_total:int,
 *   nb_bl_valides:int,
 *   nb_clients_b2b_crees:int,
 *   heures_indicatif:?int,
 *   trace_commandes:bool,
 *   trace_commandes_creees:bool,
 *   trace_devis:bool,
 *   trace_factures_devis:bool,
 *   trace_clients_b2b:bool
 * }
 */
function get_stats_activite_par_admin_id($admin_id) {
    global $db;
    $admin_id = (int) $admin_id;
    $out = [
        'nb_commandes_traitees' => 0,
        'nb_commandes_creees' => 0,
        'nb_devis' => 0,
        'nb_factures_devis' => 0,
        'nb_factures_mensuelles' => 0,
        'nb_bl_total' => 0,
        'nb_bl_valides' => 0,
        'nb_clients_b2b_crees' => 0,
        'heures_indicatif' => null,
        'trace_commandes' => false,
        'trace_commandes_creees' => false,
        'trace_devis' => false,
        'trace_factures_devis' => false,
        'trace_clients_b2b' => false,
    ];
    if ($admin_id <= 0) {
        return $out;
    }

    try {
        $stmt = $db->prepare('SELECT date_creation, derniere_connexion FROM admin WHERE id = :id');
        $stmt->execute(['id' => $admin_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['date_creation'])) {
            $stmtH = $db->prepare(
                'SELECT TIMESTAMPDIFF(HOUR, date_creation, COALESCE(derniere_connexion, NOW())) AS h
                 FROM admin WHERE id = :id'
            );
            $stmtH->execute(['id' => $admin_id]);
            $h = $stmtH->fetch(PDO::FETCH_ASSOC);
            if ($h && isset($h['h'])) {
                $out['heures_indicatif'] = max(0, (int) $h['h']);
            }
        }
    } catch (PDOException $e) {
        error_log('[get_stats_activite_par_admin_id admin] ' . $e->getMessage());
    }

    if (admin_activite_column_exists('bons_livraison', 'admin_createur_id')) {
        try {
            $stmt = $db->prepare(
                'SELECT COUNT(*) AS n, SUM(CASE WHEN statut = \'valide\' THEN 1 ELSE 0 END) AS nv
                 FROM bons_livraison WHERE admin_createur_id = :aid'
            );
            $stmt->execute(['aid' => $admin_id]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                $out['nb_bl_total'] = (int) ($r['n'] ?? 0);
                $out['nb_bl_valides'] = (int) ($r['nv'] ?? 0);
            }
        } catch (PDOException $e) {
            error_log('[get_stats_activite_par_admin_id bl] ' . $e->getMessage());
        }
    }

    if (admin_activite_column_exists('factures_mensuelles', 'admin_createur_id')) {
        try {
            $stmt = $db->prepare('SELECT COUNT(*) FROM factures_mensuelles WHERE admin_createur_id = :aid');
            $stmt->execute(['aid' => $admin_id]);
            $out['nb_factures_mensuelles'] = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('[get_stats_activite_par_admin_id fm] ' . $e->getMessage());
        }
    }

    if (admin_activite_column_exists('devis', 'admin_createur_id')) {
        $out['trace_devis'] = true;
        try {
            $stmt = $db->prepare('SELECT COUNT(*) FROM devis WHERE admin_createur_id = :aid');
            $stmt->execute(['aid' => $admin_id]);
            $out['nb_devis'] = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('[get_stats_activite_par_admin_id devis] ' . $e->getMessage());
        }
    }

    if (admin_activite_column_exists('factures_devis', 'admin_createur_id')) {
        $out['trace_factures_devis'] = true;
        try {
            $stmt = $db->prepare('SELECT COUNT(*) FROM factures_devis WHERE admin_createur_id = :aid');
            $stmt->execute(['aid' => $admin_id]);
            $out['nb_factures_devis'] = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('[get_stats_activite_par_admin_id fd] ' . $e->getMessage());
        }
    }

    if (admin_activite_column_exists('clients_b2b', 'admin_createur_id')) {
        $out['trace_clients_b2b'] = true;
        try {
            $stmt = $db->prepare('SELECT COUNT(*) FROM clients_b2b WHERE admin_createur_id = :aid');
            $stmt->execute(['aid' => $admin_id]);
            $out['nb_clients_b2b_crees'] = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('[get_stats_activite_par_admin_id b2b] ' . $e->getMessage());
        }
    }

    if (admin_activite_column_exists('commandes', 'admin_createur_id')) {
        $out['trace_commandes_creees'] = true;
        try {
            $stmt = $db->prepare('SELECT COUNT(*) FROM commandes WHERE admin_createur_id = :aid');
            $stmt->execute(['aid' => $admin_id]);
            $out['nb_commandes_creees'] = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('[get_stats_activite_par_admin_id cmd crea] ' . $e->getMessage());
        }
    }

    if (admin_activite_column_exists('commandes', 'admin_dernier_traitement_id')) {
        $out['trace_commandes'] = true;
        try {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM commandes WHERE admin_dernier_traitement_id = :aid'
            );
            $stmt->execute(['aid' => $admin_id]);
            $out['nb_commandes_traitees'] = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('[get_stats_activite_par_admin_id cmd] ' . $e->getMessage());
        }
    }

    return $out;
}

/**
 * Types de listes disponibles pour employe-activite-liste.php
 *
 * @return array<string, string> slug => libellé
 */
function get_activite_liste_types_libelles() {
    return [
        'commandes_creees' => 'Commandes créées (saisie manuelle)',
        'commandes_traitees' => 'Commandes (dernier traitement de statut)',
        'devis' => 'Devis créés',
        'factures_devis' => 'Factures générées (devis)',
        'bl' => 'Bons de livraison créés',
        'factures_mensuelles' => 'Factures mensuelles HT',
        'clients_b2b' => 'Clients B2B enregistrés',
    ];
}

/**
 * @param string $type Clé parmi get_activite_liste_types_libelles()
 * @param int $limit Nombre max de lignes (1–500)
 * @return array<int, array<string, mixed>>
 */
function get_liste_activite_par_admin($admin_id, $type, $limit = 200) {
    global $db;
    $admin_id = (int) $admin_id;
    $limit = max(1, min(500, (int) $limit));
    if ($admin_id <= 0) {
        return [];
    }
    $types_ok = array_keys(get_activite_liste_types_libelles());
    if (!in_array($type, $types_ok, true)) {
        return [];
    }

    try {
        switch ($type) {
            case 'commandes_creees':
                if (!admin_activite_column_exists('commandes', 'admin_createur_id')) {
                    return [];
                }
                $stmt = $db->prepare(
                    'SELECT id, numero_commande, date_commande, statut, montant_total
                     FROM commandes WHERE admin_createur_id = :aid
                     ORDER BY date_commande DESC LIMIT ' . $limit
                );
                $stmt->execute(['aid' => $admin_id]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            case 'commandes_traitees':
                if (!admin_activite_column_exists('commandes', 'admin_dernier_traitement_id')) {
                    return [];
                }
                $stmt = $db->prepare(
                    'SELECT id, numero_commande, date_commande, statut, montant_total
                     FROM commandes WHERE admin_dernier_traitement_id = :aid
                     ORDER BY date_commande DESC LIMIT ' . $limit
                );
                $stmt->execute(['aid' => $admin_id]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            case 'devis':
                if (!admin_activite_column_exists('devis', 'admin_createur_id')) {
                    return [];
                }
                $stmt = $db->prepare(
                    'SELECT id, numero_devis, statut, montant_total, date_creation
                     FROM devis WHERE admin_createur_id = :aid
                     ORDER BY date_creation DESC LIMIT ' . $limit
                );
                $stmt->execute(['aid' => $admin_id]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            case 'factures_devis':
                if (!admin_activite_column_exists('factures_devis', 'admin_createur_id')) {
                    return [];
                }
                $stmt = $db->prepare(
                    'SELECT id, devis_id, numero_facture, date_facture, montant_total, date_creation
                     FROM factures_devis WHERE admin_createur_id = :aid
                     ORDER BY date_creation DESC LIMIT ' . $limit
                );
                $stmt->execute(['aid' => $admin_id]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            case 'bl':
                if (!admin_activite_column_exists('bons_livraison', 'admin_createur_id')) {
                    return [];
                }
                $stmt = $db->prepare(
                    'SELECT id, numero_bl, statut, date_bl, total_ht, date_creation, devis_id, client_b2b_id
                     FROM bons_livraison WHERE admin_createur_id = :aid
                     ORDER BY date_creation DESC LIMIT ' . $limit
                );
                $stmt->execute(['aid' => $admin_id]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            case 'factures_mensuelles':
                if (!admin_activite_column_exists('factures_mensuelles', 'admin_createur_id')) {
                    return [];
                }
                $stmt = $db->prepare(
                    'SELECT id, numero_facture, client_b2b_id, annee, mois, total_ht, statut, date_emission, date_creation
                     FROM factures_mensuelles WHERE admin_createur_id = :aid
                     ORDER BY date_creation DESC LIMIT ' . $limit
                );
                $stmt->execute(['aid' => $admin_id]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            case 'clients_b2b':
                if (!admin_activite_column_exists('clients_b2b', 'admin_createur_id')) {
                    return [];
                }
                $stmt = $db->prepare(
                    'SELECT id, raison_sociale, telephone, email, statut, date_creation
                     FROM clients_b2b WHERE admin_createur_id = :aid
                     ORDER BY date_creation DESC LIMIT ' . $limit
                );
                $stmt->execute(['aid' => $admin_id]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            default:
                return [];
        }
    } catch (PDOException $e) {
        error_log('[get_liste_activite_par_admin] ' . $e->getMessage());
        return [];
    }
}
