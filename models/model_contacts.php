<?php
/**
 * Modèle pour la gestion des contacts (manuels)
 */
require_once __DIR__ . '/../conn/conn.php';

/**
 * @return array{0: string, 1: array<string, string>}
 */
function contacts_build_search_clause(PDO $db, $recherche)
{
    $sql = ' WHERE 1=1';
    $params = [];
    if (!empty(trim($recherche ?? ''))) {
        $term = '%' . trim($recherche) . '%';
        $sql .= ' AND (nom LIKE :term OR prenom LIKE :term2 OR telephone LIKE :term3 OR email LIKE :term4';
        try {
            $c = $db->query("SHOW COLUMNS FROM contacts LIKE 'adresse'");
            if ($c && $c->fetch()) {
                $sql .= ' OR adresse LIKE :term5';
                $params['term5'] = $term;
            }
        } catch (PDOException $e) {
        }
        $sql .= ')';
        $params['term'] = $term;
        $params['term2'] = $term;
        $params['term3'] = $term;
        $params['term4'] = $term;
    }
    return [$sql, $params];
}

/**
 * @param string|null $recherche Recherche sur nom, prénom, téléphone, email, adresse
 */
function get_contacts_count($recherche = null) {
    global $db;
    try {
        [$where, $params] = contacts_build_search_clause($db, $recherche);
        $stmt = $db->prepare('SELECT COUNT(*) AS n FROM contacts' . $where);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['n'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function get_contacts_page($recherche = null, $page = 1, $per_page = 10) {
    global $db;
    $page = max(1, (int) $page);
    $per_page = max(1, min(100, (int) $per_page));
    $offset = ($page - 1) * $per_page;
    try {
        [$where, $params] = contacts_build_search_clause($db, $recherche);
        $sql = 'SELECT * FROM contacts' . $where . ' ORDER BY nom ASC, prenom ASC LIMIT :lim OFFSET :off';
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère tous les contacts (sans pagination) — préférer get_contacts_page en liste admin
 *
 * @param string|null $recherche Recherche sur nom, prénom, téléphone
 */
function get_all_contacts($recherche = null) {
    global $db;
    try {
        [$where, $params] = contacts_build_search_clause($db, $recherche);
        $sql = 'SELECT * FROM contacts' . $where . ' ORDER BY nom ASC, prenom ASC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère un contact par téléphone (chiffres uniquement, comparaison stricte)
 */
function get_contact_by_telephone($telephone) {
    global $db;
    $tel = preg_replace('/\D+/', '', $telephone ?? '');
    if ($tel === '') {
        return false;
    }
    try {
        $stmt = $db->query('SELECT * FROM contacts');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $t2 = preg_replace('/\D+/', '', $r['telephone'] ?? '');
            if ($t2 !== '' && $t2 === $tel) {
                return $r;
            }
        }
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Carnet contacts : si aucune ligne avec ce numéro (normalisé), crée le contact.
 * Si le numéro existe déjà, ne fait rien et retourne l'id existant.
 * Utilisé lors de la création d'un bon de livraison ou d'un devis.
 *
 * @return int|false id du contact existant ou créé
 */
function ensure_contact_from_bl($nom, $prenom, $telephone, $email = null) {
    $telephone = trim($telephone ?? '');
    if ($telephone === '') {
        return false;
    }
    $existing = get_contact_by_telephone($telephone);
    if ($existing) {
        return (int) $existing['id'];
    }
    $id = create_contact(
        trim($nom ?? ''),
        trim($prenom ?? ''),
        $telephone,
        $email && trim($email) !== '' ? trim($email) : null
    );
    return $id ? (int) $id : false;
}

/**
 * Vérifie si un téléphone existe (users ou contacts)
 */
function telephone_exists_in_users_or_contacts($telephone) {
    global $db;
    $tel = preg_replace('/\D/', '', $telephone);
    if (empty($tel) || strlen($tel) < 8) {
        return false;
    }
    try {
        $stmt = $db->prepare("
            SELECT 1 FROM users WHERE REPLACE(REPLACE(REPLACE(COALESCE(telephone,''), ' ', ''), '-', ''), '+', '') LIKE :tel
            UNION ALL
            SELECT 1 FROM contacts WHERE REPLACE(REPLACE(REPLACE(COALESCE(telephone,''), ' ', ''), '-', ''), '+', '') LIKE :tel2
            LIMIT 1
        ");
        $stmt->execute(['tel' => '%' . $tel . '%', 'tel2' => '%' . $tel . '%']);
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère un contact par ID
 */
function get_contact_by_id($id) {
    global $db;
    try {
        $stmt = $db->prepare('SELECT * FROM contacts WHERE id = :id');
        $stmt->execute(['id' => (int) $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

function contacts_normalize_type_bl($code)
{
    return (($code ?? '') === 'vip') ? 'vip' : 'standard';
}

function contacts_normalize_plafond_ht($val)
{
    $n = (float) str_replace(',', '.', (string) ($val ?? '0'));
    return round(max(0, $n), 2);
}

/**
 * Téléphone normalisé (chiffres uniquement) pour rapprochements devis / B2B.
 */
function contacts_normalize_tel_digits($telephone)
{
    return preg_replace('/\D+/', '', (string) ($telephone ?? ''));
}

/**
 * Enrichit chaque contact avec des compteurs compta (factures devis payées / impayées,
 * factures mensuelles BL payées / autres statuts) et id client B2B le cas échéant.
 * Requêtes agrégées pour éviter N× scans.
 *
 * @param array<int, array<string, mixed>> $contacts
 * @return array<int, array<string, mixed>>
 */
function contacts_list_with_compta_stats(array $contacts)
{
    global $db;
    if (!$db || empty($contacts)) {
        return $contacts;
    }

    $by_tel = [];
    foreach ($contacts as $c) {
        $t = contacts_normalize_tel_digits($c['telephone'] ?? '');
        if ($t === '') {
            continue;
        }
        if (!isset($by_tel[$t])) {
            $by_tel[$t] = ['fd_payees' => 0, 'fd_impayees' => 0, 'b2b_id' => 0, 'fm_payees' => 0, 'fm_impayees' => 0];
        }
    }

    try {
        $stmt = $db->query('SELECT id, telephone FROM clients_b2b');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $t = contacts_normalize_tel_digits($r['telephone'] ?? '');
            if ($t !== '' && isset($by_tel[$t])) {
                $by_tel[$t]['b2b_id'] = (int) $r['id'];
            }
        }
    } catch (PDOException $e) {
    }

    try {
        $stmt = $db->query('SELECT fd.payee, d.client_telephone FROM factures_devis fd INNER JOIN devis d ON d.id = fd.devis_id');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $t = contacts_normalize_tel_digits($row['client_telephone'] ?? '');
            if ($t === '' || !isset($by_tel[$t])) {
                continue;
            }
            if (!empty($row['payee'])) {
                $by_tel[$t]['fd_payees']++;
            } else {
                $by_tel[$t]['fd_impayees']++;
            }
        }
    } catch (PDOException $e) {
    }

    require_once __DIR__ . '/model_factures_mensuelles.php';
    $fmByCid = [];
    if (function_exists('factures_mensuelles_table_ok') && factures_mensuelles_table_ok()) {
        try {
            $stmt = $db->query('SELECT client_b2b_id, statut FROM factures_mensuelles');
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $cid = (int) ($row['client_b2b_id'] ?? 0);
                if ($cid <= 0) {
                    continue;
                }
                if (!isset($fmByCid[$cid])) {
                    $fmByCid[$cid] = ['fm_payees' => 0, 'fm_impayees' => 0];
                }
                if (($row['statut'] ?? '') === 'payee') {
                    $fmByCid[$cid]['fm_payees']++;
                } else {
                    $fmByCid[$cid]['fm_impayees']++;
                }
            }
        } catch (PDOException $e) {
        }
    }

    foreach ($by_tel as $t => &$st) {
        $st['fm_payees'] = 0;
        $st['fm_impayees'] = 0;
        $bid = (int) ($st['b2b_id'] ?? 0);
        if ($bid > 0 && isset($fmByCid[$bid])) {
            $st['fm_payees'] = $fmByCid[$bid]['fm_payees'];
            $st['fm_impayees'] = $fmByCid[$bid]['fm_impayees'];
        }
    }
    unset($st);

    $out = [];
    foreach ($contacts as $c) {
        $t = contacts_normalize_tel_digits($c['telephone'] ?? '');
        $stats = [
            'fd_payees' => 0,
            'fd_impayees' => 0,
            'fm_payees' => 0,
            'fm_impayees' => 0,
            'b2b_id' => 0,
        ];
        if ($t !== '' && isset($by_tel[$t])) {
            $stats['fd_payees'] = (int) $by_tel[$t]['fd_payees'];
            $stats['fd_impayees'] = (int) $by_tel[$t]['fd_impayees'];
            $stats['fm_payees'] = (int) $by_tel[$t]['fm_payees'];
            $stats['fm_impayees'] = (int) $by_tel[$t]['fm_impayees'];
            $stats['b2b_id'] = (int) $by_tel[$t]['b2b_id'];
        }
        $c['_compta'] = $stats;
        $out[] = $c;
    }
    return $out;
}

/**
 * Met à jour un contact
 *
 * @param string|null $adresse
 */
function update_contact($id, $nom, $prenom, $telephone, $email = null, $adresse = null, $plafond_bl_cumul_ht = 0.0) {
    global $db;
    $pl = contacts_normalize_plafond_ht($plafond_bl_cumul_ht);
    $addr = $adresse !== null && trim((string) $adresse) !== '' ? trim((string) $adresse) : null;
    try {
        $stmt = $db->prepare('UPDATE contacts SET nom = :nom, prenom = :prenom, telephone = :telephone, email = :email, adresse = :adresse, plafond_bl_cumul_ht = :pl WHERE id = :id');
        return $stmt->execute([
            'id' => (int) $id,
            'nom' => trim($nom),
            'prenom' => trim($prenom),
            'telephone' => trim($telephone),
            'email' => $email && trim($email) !== '' ? trim($email) : null,
            'adresse' => $addr,
            'pl' => $pl,
        ]);
    } catch (PDOException $e) {
        // colonnes absentes : repli sans adresse/plafond
        try {
            $stmt = $db->prepare('UPDATE contacts SET nom = :nom, prenom = :prenom, telephone = :telephone, email = :email WHERE id = :id');
            return $stmt->execute([
                'id' => (int) $id,
                'nom' => trim($nom),
                'prenom' => trim($prenom),
                'telephone' => trim($telephone),
                'email' => $email && trim($email) !== '' ? trim($email) : null,
            ]);
        } catch (PDOException $e2) {
            return false;
        }
    }
}

/**
 * Crée un contact
 *
 * @param string|null $adresse
 */
function create_contact($nom, $prenom, $telephone, $email = null, $adresse = null, $plafond_bl_cumul_ht = 0.0) {
    global $db;
    $pl = contacts_normalize_plafond_ht($plafond_bl_cumul_ht);
    $addr = $adresse !== null && trim((string) $adresse) !== '' ? trim((string) $adresse) : null;
    try {
        $stmt = $db->prepare('INSERT INTO contacts (nom, prenom, telephone, email, adresse, plafond_bl_cumul_ht) VALUES (:nom, :prenom, :telephone, :email, :adresse, :pl)');
        $stmt->execute([
            'nom' => trim($nom),
            'prenom' => trim($prenom),
            'telephone' => trim($telephone),
            'email' => $email && trim($email) !== '' ? trim($email) : null,
            'adresse' => $addr,
            'pl' => $pl,
        ]);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        try {
            $stmt = $db->prepare('INSERT INTO contacts (nom, prenom, telephone, email, type_client_bl) VALUES (:nom, :prenom, :telephone, :email, :tb)');
            $stmt->execute([
                'nom' => trim($nom),
                'prenom' => trim($prenom),
                'telephone' => trim($telephone),
                'email' => $email && trim($email) !== '' ? trim($email) : null,
                'tb' => 'standard',
            ]);
            return $db->lastInsertId();
        } catch (PDOException $e2) {
            return false;
        }
    }
}

/**
 * Recherche clients (users + contacts) pour commande manuelle
 */
function search_clients_for_commande($recherche, $limit = 20) {
    global $db;
    $term = '%' . trim($recherche) . '%';
    if (strlen(trim($recherche)) < 1) {
        return [];
    }
    try {
        $stmt = $db->prepare("
            (SELECT id, nom, prenom, telephone, email, 'user' AS source, 'standard' AS type_client_bl, 0 AS plafond_bl_cumul_ht FROM users WHERE statut = 'actif' AND (nom LIKE :t1 OR prenom LIKE :t2 OR email LIKE :t3 OR telephone LIKE :t4))
            UNION ALL
            (SELECT id, nom, prenom, telephone, email, 'contact' AS source,
                COALESCE(type_client_bl, 'standard') AS type_client_bl,
                COALESCE(plafond_bl_cumul_ht, 0) AS plafond_bl_cumul_ht
                FROM contacts WHERE nom LIKE :t5 OR prenom LIKE :t6 OR email LIKE :t7 OR telephone LIKE :t8)
            LIMIT :limit
        ");
        $stmt->bindValue('t1', $term, PDO::PARAM_STR);
        $stmt->bindValue('t2', $term, PDO::PARAM_STR);
        $stmt->bindValue('t3', $term, PDO::PARAM_STR);
        $stmt->bindValue('t4', $term, PDO::PARAM_STR);
        $stmt->bindValue('t5', $term, PDO::PARAM_STR);
        $stmt->bindValue('t6', $term, PDO::PARAM_STR);
        $stmt->bindValue('t7', $term, PDO::PARAM_STR);
        $stmt->bindValue('t8', $term, PDO::PARAM_STR);
        $stmt->bindValue('limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        try {
            $stmt = $db->prepare("
                (SELECT id, nom, prenom, telephone, email, 'user' AS source, 'standard' AS type_client_bl FROM users WHERE statut = 'actif' AND (nom LIKE :t1 OR prenom LIKE :t2 OR email LIKE :t3 OR telephone LIKE :t4))
                UNION ALL
                (SELECT id, nom, prenom, telephone, email, 'contact' AS source,
                    COALESCE(type_client_bl, 'standard') AS type_client_bl FROM contacts WHERE nom LIKE :t5 OR prenom LIKE :t6 OR email LIKE :t7 OR telephone LIKE :t8)
                LIMIT :limit
            ");
            $stmt->bindValue('t1', $term, PDO::PARAM_STR);
            $stmt->bindValue('t2', $term, PDO::PARAM_STR);
            $stmt->bindValue('t3', $term, PDO::PARAM_STR);
            $stmt->bindValue('t4', $term, PDO::PARAM_STR);
            $stmt->bindValue('t5', $term, PDO::PARAM_STR);
            $stmt->bindValue('t6', $term, PDO::PARAM_STR);
            $stmt->bindValue('t7', $term, PDO::PARAM_STR);
            $stmt->bindValue('t8', $term, PDO::PARAM_STR);
            $stmt->bindValue('limit', (int) $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e2) {
            return [];
        }
    }
}
