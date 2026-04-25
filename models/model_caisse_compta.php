<?php
/**
 * Comptabilité — ventes caisse (lecture, filtres)
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_caisse.php';

/**
 * Libellé français du mode de paiement
 */
function caisse_compta_libelle_mode($mode)
{
    $m = (string) $mode;
    $map = [
        'especes' => 'Espèces',
        'carte' => 'Carte bancaire',
        'mobile_money' => 'Mobile money',
        'cheque' => 'Chèque',
        'mixte' => 'Mixte',
        'autre' => 'Autre',
    ];
    return $map[$m] ?? $m;
}

/**
 * Liste des administrateurs actifs (filtre caisse)
 */
function caisse_compta_liste_admins_actifs()
{
    global $db;
    try {
        $stmt = $db->query("
            SELECT id, nom, prenom, email
            FROM admin
            WHERE statut = 'actif'
            ORDER BY nom ASC, prenom ASC
        ");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Ventes caisse filtrées pour la comptabilité
 *
 * @param array $params date_debut, date_fin (Y-m-d), mode_paiement ('' = tous), admin_id (0 = tous), q (recherche ticket/notes)
 * @return array<int, array<string,mixed>>
 */
function caisse_compta_get_ventes_filtrees(array $params)
{
    global $db;

    if (!caisse_tables_exist()) {
        return [];
    }

    $date_debut = $params['date_debut'] ?? date('Y-m-01');
    $date_fin = $params['date_fin'] ?? date('Y-m-d');
    $mode = isset($params['mode_paiement']) ? trim((string) $params['mode_paiement']) : '';
    $admin_id = isset($params['admin_id']) ? (int) $params['admin_id'] : 0;
    $q = isset($params['q']) ? trim((string) $params['q']) : '';
    $limit = isset($params['limit']) ? max(1, min(500, (int) $params['limit'])) : 500;

    $modes_ok = ['especes', 'carte', 'mobile_money', 'cheque', 'mixte', 'autre'];
    if ($mode !== '' && !in_array($mode, $modes_ok, true)) {
        $mode = '';
    }

    // Ventes comptabilisées : payées uniquement ; période selon date d’encaissement (ou date_vente si absente)
    $where = [
        "DATE(COALESCE(v.date_encaissement, v.date_vente)) >= :d1",
        "DATE(COALESCE(v.date_encaissement, v.date_vente)) <= :d2",
        "(v.statut = 'paye' OR v.statut IS NULL)",
    ];
    $bind = [
        'd1' => $date_debut,
        'd2' => $date_fin,
    ];

    if ($mode !== '') {
        $where[] = 'v.mode_paiement = :mode';
        $bind['mode'] = $mode;
    }
    if ($admin_id > 0) {
        $where[] = 'v.admin_id = :aid';
        $bind['aid'] = $admin_id;
    }
    if ($q !== '') {
        $where[] = '(v.numero_ticket LIKE :q OR (v.notes IS NOT NULL AND v.notes LIKE :q2))';
        $bind['q'] = '%' . $q . '%';
        $bind['q2'] = '%' . $q . '%';
    }

    $sql = '
        SELECT v.*, a.nom AS admin_nom, a.prenom AS admin_prenom
        FROM caisse_ventes v
        LEFT JOIN admin a ON a.id = v.admin_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY v.date_vente DESC, v.id DESC
        LIMIT ' . (int) $limit;

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($bind);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[caisse_compta_get_ventes_filtrees] ' . $e->getMessage());
        return [];
    }
}

/**
 * Totaux globaux et par mode de paiement
 *
 * @return array{total_ttc:float, nb:int, par_mode:array<string,array{nb:int,total:float}>}
 */
function caisse_compta_calculer_totaux(array $ventes)
{
    $total_ttc = 0.0;
    $nb = count($ventes);
    $par_mode = [];

    foreach ($ventes as $row) {
        $mt = (float) ($row['montant_total'] ?? 0);
        $total_ttc += $mt;
        $m = (string) ($row['mode_paiement'] ?? '');
        if (!isset($par_mode[$m])) {
            $par_mode[$m] = ['nb' => 0, 'total' => 0.0];
        }
        $par_mode[$m]['nb']++;
        $par_mode[$m]['total'] += $mt;
    }

    foreach ($par_mode as $k => $v) {
        $par_mode[$k]['total'] = round($v['total'], 2);
    }

    return [
        'total_ttc' => round($total_ttc, 2),
        'nb' => $nb,
        'par_mode' => $par_mode,
    ];
}

/**
 * Historique des encaissements (tickets payés) — liste avec vendeur + caissier
 *
 * @param array $params date_debut, date_fin (Y-m-d), mode_paiement, caissier_id (0 = tous), q, limit
 * @return array<int, array<string, mixed>>
 */
function caisse_encaissements_historique_fetch(array $params)
{
    global $db;

    if (!caisse_tables_exist()) {
        return [];
    }

    $date_debut = isset($params['date_debut']) ? trim((string) $params['date_debut']) : '';
    $date_fin = isset($params['date_fin']) ? trim((string) $params['date_fin']) : '';
    $mode = isset($params['mode_paiement']) ? trim((string) $params['mode_paiement']) : '';
    $caissier_id = isset($params['caissier_id']) ? (int) $params['caissier_id'] : 0;
    $q = isset($params['q']) ? trim((string) $params['q']) : '';
    $limit = isset($params['limit']) ? max(1, min(2000, (int) $params['limit'])) : 500;

    $modes_ok = ['especes', 'carte', 'mobile_money', 'cheque', 'mixte', 'autre'];
    if ($mode !== '' && !in_array($mode, $modes_ok, true)) {
        $mode = '';
    }

    $where = [
        "DATE(COALESCE(v.date_encaissement, v.date_vente)) >= :d1",
        "DATE(COALESCE(v.date_encaissement, v.date_vente)) <= :d2",
        "(v.statut = 'paye' OR v.statut IS NULL)",
    ];
    $bind = [
        'd1' => $date_debut,
        'd2' => $date_fin,
    ];

    if ($mode !== '') {
        $where[] = 'v.mode_paiement = :mode';
        $bind['mode'] = $mode;
    }
    if ($caissier_id > 0) {
        $where[] = 'v.caissier_id = :cid';
        $bind['cid'] = $caissier_id;
    }
    if ($q !== '') {
        $where[] = '(v.numero_ticket LIKE :q OR (v.notes IS NOT NULL AND v.notes LIKE :q2))';
        $bind['q'] = '%' . $q . '%';
        $bind['q2'] = '%' . $q . '%';
    }

    $sql = '
        SELECT v.*,
               vend.nom AS vendeur_nom, vend.prenom AS vendeur_prenom,
               caiss.nom AS encaiss_nom, caiss.prenom AS encaiss_prenom
        FROM caisse_ventes v
        LEFT JOIN admin vend ON vend.id = v.admin_id
        LEFT JOIN admin caiss ON caiss.id = v.caissier_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY COALESCE(v.date_encaissement, v.date_vente) DESC, v.id DESC
        LIMIT ' . (int) $limit;

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($bind);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[caisse_encaissements_historique_fetch] ' . $e->getMessage());
        return [];
    }
}
