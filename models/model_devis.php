<?php
/**
 * Modèle pour la gestion des devis
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_admin_activite.php';
require_once __DIR__ . '/../includes/fiscal_tva.php';

/**
 * Colonnes TVA présentes sur la table devis
 */
function devis_tva_columns_ok() {
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $ok = false;
    if (!$db) {
        return false;
    }
    try {
        $db->query('SELECT tva_incluse, taux_tva_pourcent FROM devis LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * Colonne adresse_client (migration add_devis_bl_adresse_client)
 */
function devis_adresse_client_column_ok() {
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $ok = false;
    if (!$db) {
        return false;
    }
    try {
        $db->query('SELECT adresse_client FROM devis LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * Net HT d'un devis : somme des lignes + frais de livraison
 */
function devis_calcul_net_ht($devis_id) {
    global $db;
    $devis_id = (int) $devis_id;
    if ($devis_id <= 0) {
        return 0.0;
    }
    try {
        $stmt = $db->prepare('SELECT COALESCE(SUM(prix_total), 0) FROM devis_produits WHERE devis_id = :id');
        $stmt->execute(['id' => $devis_id]);
        $s = (float) $stmt->fetchColumn();
        $d = get_devis_by_id($devis_id);
        $frais = $d ? (float) ($d['frais_livraison'] ?? 0) : 0.0;
        return round($s + $frais, 2);
    } catch (PDOException $e) {
        return 0.0;
    }
}

/**
 * Génère un numéro de devis unique (format DEV + 5 chiffres)
 */
function generate_numero_devis() {
    global $db;
    try {
        $stmt = $db->query("SELECT MAX(id) as max_id FROM devis");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $next = ($row && $row['max_id']) ? (int) $row['max_id'] + 1 : 1;
        return 'DEV' . str_pad($next, 5, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        return 'DEV' . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
    }
}

/**
 * Crée un devis
 * @param array $items [['produit_id'=>int, 'quantite'=>int, 'prix_unitaire'=>float, 'nom_produit'=>string|null], ...]
 * @param string $client_nom
 * @param string $client_prenom
 * @param string $client_telephone
 * @param string $adresse_livraison
 * @param string|null $client_email
 * @param string|null $notes
 * @param int|null $zone_livraison_id
 * @param float $frais_livraison
 * @param int|null $user_id
 * @param int|null $admin_createur_id Admin ayant créé le devis (traçabilité)
 * @param bool $tva_incluse Total à payer TTC (HT + TVA) si true — comme la caisse
 * @param string|null $adresse_client Adresse postale / siège du client (optionnel)
 * @return array|false ['success'=>true, 'devis_id'=>int, 'numero_devis'=>string] ou false
 */
function create_devis($items, $client_nom, $client_prenom, $client_telephone, $adresse_livraison, $client_email = null, $notes = null, $zone_livraison_id = null, $frais_livraison = 0, $user_id = null, $admin_createur_id = null, $tva_incluse = false, $adresse_client = null) {
    global $db;

    if (empty($items) || empty(trim($client_nom)) || empty(trim($client_telephone))) {
        return false;
    }

    $net_ht = 0;
    foreach ($items as $it) {
        $qte = (int) ($it['quantite'] ?? 1);
        $pu = (float) str_replace(',', '.', $it['prix_unitaire'] ?? 0);
        $net_ht += $qte * $pu;
    }
    $frais_livraison = (float) ($frais_livraison ?? 0);
    $net_ht += $frais_livraison;
    $net_ht = round($net_ht, 2);

    $tva_flag = (bool) $tva_incluse;
    if (!devis_tva_columns_ok()) {
        $montant_total = $net_ht;
        $tva_flag = false;
    } else {
        $fiscal = fiscal_decomposer_net_ht($net_ht, $tva_flag);
        $montant_total = $fiscal['montant_ttc'];
    }
    $taux_tva_stocke = fiscal_taux_tva_pourcent();

    $numero = generate_numero_devis();
    try {
        $stmt = $db->prepare("SELECT id FROM devis WHERE numero_devis = :num");
        $stmt->execute(['num' => $numero]);
        if ($stmt->fetch()) {
            $numero = generate_numero_devis() . '-' . substr(uniqid(), -3);
        }

        $has_admin = admin_activite_column_exists('devis', 'admin_createur_id');
        $aid = $has_admin && $admin_createur_id !== null && (int) $admin_createur_id > 0 ? (int) $admin_createur_id : null;
        $tva_ok = devis_tva_columns_ok();

        if ($has_admin && $tva_ok) {
            $stmt = $db->prepare("
                INSERT INTO devis (
                    numero_devis, client_nom, client_prenom, client_telephone, client_email,
                    adresse_livraison, zone_livraison_id, frais_livraison, tva_incluse, taux_tva_pourcent, user_id, admin_createur_id,
                    montant_total, notes, statut
                ) VALUES (
                    :numero_devis, :client_nom, :client_prenom, :client_telephone, :client_email,
                    :adresse_livraison, :zone_livraison_id, :frais_livraison, :tva_incluse, :taux_tva_pourcent, :user_id, :admin_createur_id,
                    :montant_total, :notes, 'brouillon'
                )
            ");
            $stmt->execute([
                'numero_devis' => $numero,
                'client_nom' => trim($client_nom),
                'client_prenom' => trim($client_prenom),
                'client_telephone' => trim($client_telephone),
                'client_email' => $client_email && trim($client_email) !== '' ? trim($client_email) : null,
                'adresse_livraison' => trim($adresse_livraison),
                'zone_livraison_id' => $zone_livraison_id && (int) $zone_livraison_id > 0 ? (int) $zone_livraison_id : null,
                'frais_livraison' => $frais_livraison,
                'tva_incluse' => $tva_flag ? 1 : 0,
                'taux_tva_pourcent' => $taux_tva_stocke,
                'user_id' => $user_id && (int) $user_id > 0 ? (int) $user_id : null,
                'admin_createur_id' => $aid,
                'montant_total' => $montant_total,
                'notes' => $notes ? trim($notes) : null
            ]);
        } elseif ($has_admin) {
            $stmt = $db->prepare("
                INSERT INTO devis (
                    numero_devis, client_nom, client_prenom, client_telephone, client_email,
                    adresse_livraison, zone_livraison_id, frais_livraison, user_id, admin_createur_id,
                    montant_total, notes, statut
                ) VALUES (
                    :numero_devis, :client_nom, :client_prenom, :client_telephone, :client_email,
                    :adresse_livraison, :zone_livraison_id, :frais_livraison, :user_id, :admin_createur_id,
                    :montant_total, :notes, 'brouillon'
                )
            ");
            $stmt->execute([
                'numero_devis' => $numero,
                'client_nom' => trim($client_nom),
                'client_prenom' => trim($client_prenom),
                'client_telephone' => trim($client_telephone),
                'client_email' => $client_email && trim($client_email) !== '' ? trim($client_email) : null,
                'adresse_livraison' => trim($adresse_livraison),
                'zone_livraison_id' => $zone_livraison_id && (int) $zone_livraison_id > 0 ? (int) $zone_livraison_id : null,
                'frais_livraison' => $frais_livraison,
                'user_id' => $user_id && (int) $user_id > 0 ? (int) $user_id : null,
                'admin_createur_id' => $aid,
                'montant_total' => $montant_total,
                'notes' => $notes ? trim($notes) : null
            ]);
        } elseif ($tva_ok) {
            $stmt = $db->prepare("
                INSERT INTO devis (
                    numero_devis, client_nom, client_prenom, client_telephone, client_email,
                    adresse_livraison, zone_livraison_id, frais_livraison, tva_incluse, taux_tva_pourcent, user_id,
                    montant_total, notes, statut
                ) VALUES (
                    :numero_devis, :client_nom, :client_prenom, :client_telephone, :client_email,
                    :adresse_livraison, :zone_livraison_id, :frais_livraison, :tva_incluse, :taux_tva_pourcent, :user_id,
                    :montant_total, :notes, 'brouillon'
                )
            ");
            $stmt->execute([
                'numero_devis' => $numero,
                'client_nom' => trim($client_nom),
                'client_prenom' => trim($client_prenom),
                'client_telephone' => trim($client_telephone),
                'client_email' => $client_email && trim($client_email) !== '' ? trim($client_email) : null,
                'adresse_livraison' => trim($adresse_livraison),
                'zone_livraison_id' => $zone_livraison_id && (int) $zone_livraison_id > 0 ? (int) $zone_livraison_id : null,
                'frais_livraison' => $frais_livraison,
                'tva_incluse' => $tva_flag ? 1 : 0,
                'taux_tva_pourcent' => $taux_tva_stocke,
                'user_id' => $user_id && (int) $user_id > 0 ? (int) $user_id : null,
                'montant_total' => $montant_total,
                'notes' => $notes ? trim($notes) : null
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO devis (
                    numero_devis, client_nom, client_prenom, client_telephone, client_email,
                    adresse_livraison, zone_livraison_id, frais_livraison, user_id,
                    montant_total, notes, statut
                ) VALUES (
                    :numero_devis, :client_nom, :client_prenom, :client_telephone, :client_email,
                    :adresse_livraison, :zone_livraison_id, :frais_livraison, :user_id,
                    :montant_total, :notes, 'brouillon'
                )
            ");
            $stmt->execute([
                'numero_devis' => $numero,
                'client_nom' => trim($client_nom),
                'client_prenom' => trim($client_prenom),
                'client_telephone' => trim($client_telephone),
                'client_email' => $client_email && trim($client_email) !== '' ? trim($client_email) : null,
                'adresse_livraison' => trim($adresse_livraison),
                'zone_livraison_id' => $zone_livraison_id && (int) $zone_livraison_id > 0 ? (int) $zone_livraison_id : null,
                'frais_livraison' => $frais_livraison,
                'user_id' => $user_id && (int) $user_id > 0 ? (int) $user_id : null,
                'montant_total' => $montant_total,
                'notes' => $notes ? trim($notes) : null
            ]);
        }
        $devis_id = (int) $db->lastInsertId();
        if ($devis_id <= 0) return false;

        if (devis_adresse_client_column_ok()) {
            $ac_ins = trim((string) ($adresse_client ?? ''));
            try {
                $u = $db->prepare('UPDATE devis SET adresse_client = :a WHERE id = :id');
                $u->execute(['a' => $ac_ins !== '' ? $ac_ins : null, 'id' => $devis_id]);
            } catch (PDOException $e) {
                error_log('[create_devis adresse_client] ' . $e->getMessage());
            }
        }

        $stmt_prod = $db->prepare("
            INSERT INTO devis_produits (devis_id, produit_id, nom_produit, quantite, prix_unitaire, prix_total)
            VALUES (:devis_id, :produit_id, :nom_produit, :quantite, :prix_unitaire, :prix_total)
        ");

        foreach ($items as $it) {
            $produit_id = (int) ($it['produit_id'] ?? 0);
            $quantite = (int) ($it['quantite'] ?? 1);
            $prix_unitaire = (float) str_replace(',', '.', $it['prix_unitaire'] ?? 0);
            if ($produit_id <= 0 || $quantite <= 0 || $prix_unitaire <= 0) continue;
            $prix_total = $quantite * $prix_unitaire;
            $nom_produit = isset($it['nom_produit']) && trim($it['nom_produit']) !== '' ? trim($it['nom_produit']) : null;
            $stmt_prod->execute([
                'devis_id' => $devis_id,
                'produit_id' => $produit_id,
                'nom_produit' => $nom_produit,
                'quantite' => $quantite,
                'prix_unitaire' => $prix_unitaire,
                'prix_total' => $prix_total
            ]);
        }

        return ['success' => true, 'devis_id' => $devis_id, 'numero_devis' => $numero];
    } catch (PDOException $e) {
        error_log('[create_devis] ' . $e->getMessage());
        return false;
    }
}

/**
 * Clé stable pour regrouper les devis d'un même client (compte ou contact)
 */
function devis_client_groupe_cle($row)
{
    $uid = isset($row['user_id']) ? (int) $row['user_id'] : 0;
    if ($uid > 0) {
        return 'u:' . $uid;
    }
    $p = preg_replace('/\D/', '', (string) ($row['client_telephone'] ?? ''));
    $e = strtolower(trim((string) ($row['client_email'] ?? '')));
    $n = strtolower(trim((string) ($row['client_nom'] ?? '') . '|' . trim((string) ($row['client_prenom'] ?? ''))));
    return 'c:' . md5($p . '|' . $e . '|' . $n);
}

/**
 * Devis non soldés côté facture (pas de facture marquée payée associée)
 * @return array<int, array<string, mixed>>
 */
function get_devis_sans_facture_payee()
{
    global $db;
    try {
        if (function_exists('factures_devis_col_payee_ok') && factures_devis_col_payee_ok()) {
            $stmt = $db->query('
                SELECT d.* FROM devis d
                WHERE NOT EXISTS (
                    SELECT 1 FROM factures_devis fd
                    WHERE fd.devis_id = d.id AND fd.payee = 1
                )
                ORDER BY d.date_creation DESC
            ');
        } else {
            $stmt = $db->query('SELECT d.* FROM devis d ORDER BY d.date_creation DESC');
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Regroupe les devis "ouverts" (non facture payée) par client
 * @return array<int, array{cle:string, label:string, email:string, telephone:string, user_id:int, nb:int, derniere:string, devis:array}>
 */
function get_devis_agreges_par_client_non_payes()
{
    $rows = get_devis_sans_facture_payee();
    $groups = [];
    foreach ($rows as $d) {
        $k = devis_client_groupe_cle($d);
        if (!isset($groups[$k])) {
            $groups[$k] = [
                'cle' => $k,
                'user_id' => (int) ($d['user_id'] ?? 0),
                'label' => trim((string) (($d['client_prenom'] ?? '') . ' ' . ($d['client_nom'] ?? ''))),
                'email' => (string) ($d['client_email'] ?? ''),
                'telephone' => (string) ($d['client_telephone'] ?? ''),
                'nb' => 0,
                'derniere' => (string) $d['date_creation'],
                'devis' => [],
            ];
        }
        $groups[$k]['nb']++;
        $groups[$k]['devis'][] = $d;
        if (strtotime((string) $d['date_creation']) > strtotime($groups[$k]['derniere'])) {
            $groups[$k]['derniere'] = (string) $d['date_creation'];
        }
    }
    $list = array_values($groups);
    usort($list, function ($a, $b) {
        return strtotime($b['derniere']) <=> strtotime($a['derniere']);
    });
    return $list;
}

/**
 * Récupère tous les devis
 * @param string|null $statut Filtrer par statut
 * @param array{exclude_facture_payee?:bool} $opts
 * @return array
 */
function get_all_devis($statut = null, $opts = [])
{
    global $db;
    try {
        $exclude_payee = !empty($opts['exclude_facture_payee']);
        $sql = "SELECT d.* FROM devis d WHERE 1=1";
        $params = [];
        if ($statut) {
            $sql .= " AND d.statut = :statut";
            $params['statut'] = $statut;
        }
        if ($exclude_payee && function_exists('factures_devis_col_payee_ok') && factures_devis_col_payee_ok()) {
            $sql .= " AND NOT EXISTS (SELECT 1 FROM factures_devis fd WHERE fd.devis_id = d.id AND fd.payee = 1)";
        }
        $sql .= " ORDER BY d.date_creation DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère un devis par ID
 * @param int $devis_id
 * @return array|false
 */
function get_devis_by_id($devis_id) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT * FROM devis WHERE id = :id");
        $stmt->execute(['id' => (int) $devis_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère les produits d'un devis
 * @param int $devis_id
 * @return array
 */
function get_produits_by_devis($devis_id) {
    global $db;
    try {
        $stmt = $db->prepare("
            SELECT dp.*, p.nom as produit_nom_defaut,
                   COALESCE(NULLIF(TRIM(dp.nom_produit), ''), p.nom) as produit_nom
            FROM devis_produits dp
            INNER JOIN produits p ON dp.produit_id = p.id
            WHERE dp.devis_id = :devis_id
            ORDER BY dp.id
        ");
        $stmt->execute(['devis_id' => (int) $devis_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['produit_nom'] = $r['produit_nom'] ?? $r['produit_nom_defaut'] ?? '';
        }
        return $rows ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Met à jour un devis (produits + infos)
 * @param int $devis_id
 * @param array $items
 * @param array $infos [client_nom, client_prenom, client_telephone, adresse_livraison, ...]
 * @return bool
 */
function update_devis($devis_id, $items, $infos) {
    global $db;
    $devis_id = (int) $devis_id;
    if ($devis_id <= 0) {
        return false;
    }
    $ex = get_devis_by_id($devis_id);
    if (!$ex || ($ex['statut'] ?? '') !== 'brouillon') {
        return false;
    }

    try {
        $db->beginTransaction();

        $net_ht = 0;
        foreach ($items as $it) {
            $qte = (int) ($it['quantite'] ?? 1);
            $pu = (float) str_replace(',', '.', $it['prix_unitaire'] ?? 0);
            $net_ht += $qte * $pu;
        }
        $frais = (float) ($infos['frais_livraison'] ?? 0);
        $net_ht += $frais;
        $net_ht = round($net_ht, 2);

        $tva_flag = !empty($infos['tva_incluse']);
        if (!devis_tva_columns_ok()) {
            $montant_total = $net_ht;
            $tva_flag = false;
        } else {
            $fiscal = fiscal_decomposer_net_ht($net_ht, $tva_flag);
            $montant_total = $fiscal['montant_ttc'];
        }
        $taux_stocke = fiscal_taux_tva_pourcent();

        if (devis_tva_columns_ok()) {
            $stmt = $db->prepare("
            UPDATE devis SET
                client_nom = :client_nom, client_prenom = :client_prenom,
                client_telephone = :client_telephone, client_email = :client_email,
                adresse_livraison = :adresse_livraison, zone_livraison_id = :zone_livraison_id,
                frais_livraison = :frais_livraison, tva_incluse = :tva_incluse, taux_tva_pourcent = :taux_tva_pourcent,
                montant_total = :montant_total, notes = :notes
            WHERE id = :id
        ");
            $stmt->execute([
                'client_nom' => trim($infos['client_nom'] ?? ''),
                'client_prenom' => trim($infos['client_prenom'] ?? ''),
                'client_telephone' => trim($infos['client_telephone'] ?? ''),
                'client_email' => !empty(trim($infos['client_email'] ?? '')) ? trim($infos['client_email']) : null,
                'adresse_livraison' => trim($infos['adresse_livraison'] ?? ''),
                'zone_livraison_id' => !empty($infos['zone_livraison_id']) ? (int) $infos['zone_livraison_id'] : null,
                'frais_livraison' => $frais,
                'tva_incluse' => $tva_flag ? 1 : 0,
                'taux_tva_pourcent' => $taux_stocke,
                'montant_total' => $montant_total,
                'notes' => !empty(trim($infos['notes'] ?? '')) ? trim($infos['notes']) : null,
                'id' => $devis_id
            ]);
        } else {
            $stmt = $db->prepare("
            UPDATE devis SET
                client_nom = :client_nom, client_prenom = :client_prenom,
                client_telephone = :client_telephone, client_email = :client_email,
                adresse_livraison = :adresse_livraison, zone_livraison_id = :zone_livraison_id,
                frais_livraison = :frais_livraison, montant_total = :montant_total, notes = :notes
            WHERE id = :id
        ");
            $stmt->execute([
                'client_nom' => trim($infos['client_nom'] ?? ''),
                'client_prenom' => trim($infos['client_prenom'] ?? ''),
                'client_telephone' => trim($infos['client_telephone'] ?? ''),
                'client_email' => !empty(trim($infos['client_email'] ?? '')) ? trim($infos['client_email']) : null,
                'adresse_livraison' => trim($infos['adresse_livraison'] ?? ''),
                'zone_livraison_id' => !empty($infos['zone_livraison_id']) ? (int) $infos['zone_livraison_id'] : null,
                'frais_livraison' => $frais,
                'montant_total' => $montant_total,
                'notes' => !empty(trim($infos['notes'] ?? '')) ? trim($infos['notes']) : null,
                'id' => $devis_id
            ]);
        }

        if (devis_adresse_client_column_ok()) {
            $acu = trim((string) ($infos['adresse_client'] ?? ''));
            $stmt_ac = $db->prepare('UPDATE devis SET adresse_client = :a WHERE id = :id');
            $stmt_ac->execute(['a' => $acu !== '' ? $acu : null, 'id' => $devis_id]);
        }

        $db->prepare("DELETE FROM devis_produits WHERE devis_id = :id")->execute(['id' => $devis_id]);

        $stmt_prod = $db->prepare("
            INSERT INTO devis_produits (devis_id, produit_id, nom_produit, quantite, prix_unitaire, prix_total)
            VALUES (:devis_id, :produit_id, :nom_produit, :quantite, :prix_unitaire, :prix_total)
        ");
        foreach ($items as $it) {
            $produit_id = (int) ($it['produit_id'] ?? 0);
            $quantite = (int) ($it['quantite'] ?? 1);
            $prix_unitaire = (float) str_replace(',', '.', $it['prix_unitaire'] ?? 0);
            if ($produit_id <= 0 || $quantite <= 0 || $prix_unitaire <= 0) continue;
            $prix_total = $quantite * $prix_unitaire;
            $nom_produit = isset($it['nom_produit']) && trim($it['nom_produit']) !== '' ? trim($it['nom_produit']) : null;
            $stmt_prod->execute([
                'devis_id' => $devis_id,
                'produit_id' => $produit_id,
                'nom_produit' => $nom_produit,
                'quantite' => $quantite,
                'prix_unitaire' => $prix_unitaire,
                'prix_total' => $prix_total
            ]);
        }

        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('[update_devis] ' . $e->getMessage());
        return false;
    }
}

/**
 * Supprime un devis (uniquement si statut brouillon)
 * @param int $devis_id
 * @return bool
 */
function delete_devis($devis_id) {
    global $db;
    $devis_id = (int) $devis_id;
    if ($devis_id <= 0) {
        return false;
    }
    $d = get_devis_by_id($devis_id);
    if (!$d || ($d['statut'] ?? '') !== 'brouillon') {
        return false;
    }
    require_once __DIR__ . '/model_factures_devis.php';
    if (function_exists('get_facture_devis_by_devis') && get_facture_devis_by_devis($devis_id)) {
        return false;
    }
    try {
        $db->beginTransaction();
        $db->prepare('DELETE FROM devis_produits WHERE devis_id = :id')->execute(['id' => $devis_id]);
        $db->prepare('DELETE FROM devis WHERE id = :id')->execute(['id' => $devis_id]);
        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('[delete_devis] ' . $e->getMessage());
        return false;
    }
}
