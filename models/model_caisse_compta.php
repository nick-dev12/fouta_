<?php
/**
 * Comptabilité — ventes caisse (lecture, filtres)
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_caisse.php';

/**
 * Canaux affichés en comptabilité (hors « mixte » : les montants sont ventilés).
 *
 * @return string[]
 */
function caisse_compta_canaux_tri()
{
    return ['especes', 'carte', 'orange_money', 'wave', 'cheque', 'autre'];
}

/**
 * Libellé français du mode de paiement (ligne BDD)
 */
function caisse_compta_libelle_mode($mode)
{
    $m = (string) $mode;
    $map = [
        'especes' => 'Espèces',
        'carte' => 'Carte bancaire',
        'orange_money' => 'Orange Money',
        'wave' => 'Wave',
        'mobile_money' => 'Orange Money (ancien)',
        'cheque' => 'Chèque',
        'mixte' => 'Répartition',
        'autre' => 'Autre',
    ];

    return $map[$m] ?? $m;
}

/**
 * Libellé affiché sur un ticket : détail si paiement mixte.
 */
function caisse_compta_libelle_paiement_ticket(array $row)
{
    $mode = (string) ($row['mode_paiement'] ?? '');
    if ($mode !== 'mixte') {
        return caisse_compta_libelle_mode($mode);
    }
    $parts = [];
    $me = (float) ($row['montant_especes'] ?? 0);
    $mc = (float) ($row['montant_carte'] ?? 0);
    $mo = (float) ($row['montant_orange_money'] ?? 0);
    $mw = (float) ($row['montant_wave'] ?? 0);
    $mm = (float) ($row['montant_mobile_money'] ?? 0);
    $orange = $mo + $mm;
    if ($me >= 0.005) {
        $parts[] = 'Esp. ' . number_format($me, 0, ',', ' ');
    }
    if ($mc >= 0.005) {
        $parts[] = 'Carte ' . number_format($mc, 0, ',', ' ');
    }
    if ($orange >= 0.005) {
        $parts[] = 'OM ' . number_format($orange, 0, ',', ' ');
    }
    if ($mw >= 0.005) {
        $parts[] = 'Wave ' . number_format($mw, 0, ',', ' ');
    }

    return $parts ? implode(' · ', $parts) : '—';
}

/**
 * Montant TTC attribué à un canal pour une ligne de vente (mixte ventilé).
 */
function caisse_compta_montant_vente_canal(array $row, $canal)
{
    $canal = (string) $canal;
    $mode = (string) ($row['mode_paiement'] ?? '');
    $ttc = (float) ($row['montant_total'] ?? 0);
    $me = (float) ($row['montant_especes'] ?? 0);
    $mc = (float) ($row['montant_carte'] ?? 0);
    $mo = (float) ($row['montant_orange_money'] ?? 0);
    $mw = (float) ($row['montant_wave'] ?? 0);
    $mm = (float) ($row['montant_mobile_money'] ?? 0);
    $orange = $mo + $mm;

    if ($mode === 'mixte') {
        if ($canal === 'especes') {
            return $me >= 0.005 ? $me : 0.0;
        }
        if ($canal === 'carte') {
            return $mc >= 0.005 ? $mc : 0.0;
        }
        if ($canal === 'orange_money') {
            return $orange >= 0.005 ? $orange : 0.0;
        }
        if ($canal === 'wave') {
            return $mw >= 0.005 ? $mw : 0.0;
        }

        return 0.0;
    }

    if ($mode === 'mobile_money' && $canal === 'orange_money') {
        if ($orange >= 0.005) {
            return $orange;
        }

        return $ttc;
    }

    if ($mode !== $canal) {
        return 0.0;
    }

    if ($canal === 'especes') {
        return $me >= 0.005 ? $me : $ttc;
    }
    if ($canal === 'carte') {
        return $mc >= 0.005 ? $mc : $ttc;
    }
    if ($canal === 'orange_money') {
        return $orange >= 0.005 ? $orange : $ttc;
    }
    if ($canal === 'wave') {
        return $mw >= 0.005 ? $mw : $ttc;
    }
    if ($canal === 'cheque' || $canal === 'autre') {
        return $ttc;
    }

    return 0.0;
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
 * Clause SQL + bind pour filtrer les tickets qui touchent un canal (inclut mixte).
 *
 * @return array{0:string,1:array<string,mixed>}
 */
function caisse_compta_sql_filtre_canal($canal)
{
    $canal = (string) $canal;
    if ($canal === 'especes') {
        return [
            "(v.mode_paiement = 'especes' OR (v.mode_paiement = 'mixte' AND COALESCE(v.montant_especes,0) >= 0.005))",
            [],
        ];
    }
    if ($canal === 'carte') {
        return [
            "(v.mode_paiement = 'carte' OR (v.mode_paiement = 'mixte' AND COALESCE(v.montant_carte,0) >= 0.005))",
            [],
        ];
    }
    if ($canal === 'orange_money') {
        return [
            "(v.mode_paiement IN ('orange_money','mobile_money') OR (v.mode_paiement = 'mixte' AND (COALESCE(v.montant_orange_money,0)+COALESCE(v.montant_mobile_money,0)) >= 0.005))",
            [],
        ];
    }
    if ($canal === 'wave') {
        return [
            "(v.mode_paiement = 'wave' OR (v.mode_paiement = 'mixte' AND COALESCE(v.montant_wave,0) >= 0.005))",
            [],
        ];
    }
    if ($canal === 'cheque') {
        return ["(v.mode_paiement = 'cheque')", []];
    }
    if ($canal === 'autre') {
        return ["(v.mode_paiement = 'autre')", []];
    }

    return ['1=1', []];
}

/**
 * Ventes caisse filtrées pour la comptabilité
 *
 * @param array $params date_debut, date_fin, canal ('' = tous), mode_paiement (obsolète, ignoré si canal défini), admin_id, q, limit
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
    $canal = isset($params['canal']) ? trim((string) $params['canal']) : '';
    $admin_id = isset($params['admin_id']) ? (int) $params['admin_id'] : 0;
    $q = isset($params['q']) ? trim((string) $params['q']) : '';
    $limit = isset($params['limit']) ? max(1, min(500, (int) $params['limit'])) : 500;

    if ($canal !== '' && !in_array($canal, caisse_compta_canaux_tri(), true)) {
        $canal = '';
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

    if ($canal !== '') {
        $fc = caisse_compta_sql_filtre_canal($canal);
        $where[] = $fc[0];
        $bind = array_merge($bind, $fc[1]);
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
 * Lignes brutes (sans limite) pour agrégation des canaux sur la période.
 *
 * @return array<int, array<string,mixed>>
 */
function caisse_compta_fetch_lignes_brutes_periode($date_debut, $date_fin, $admin_id = 0, $q = '')
{
    global $db;
    if (!caisse_tables_exist()) {
        return [];
    }
    $where = [
        "DATE(COALESCE(v.date_encaissement, v.date_vente)) >= :d1",
        "DATE(COALESCE(v.date_encaissement, v.date_vente)) <= :d2",
        "(v.statut = 'paye' OR v.statut IS NULL)",
    ];
    $bind = ['d1' => $date_debut, 'd2' => $date_fin];
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
        SELECT v.*
        FROM caisse_ventes v
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY v.date_vente DESC, v.id DESC
    ';
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($bind);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[caisse_compta_fetch_lignes_brutes_periode] ' . $e->getMessage());

        return [];
    }
}

/**
 * Synthèse montants par canal (mixte ventilé), sans plafond de lignes.
 *
 * @return array{total_ttc:float, nb:int, par_canal:array<string,array{total:float,nb:int}>}
 */
function caisse_compta_agreger_canaux_periode($date_debut, $date_fin, $admin_id = 0, $q = '')
{
    $rows = caisse_compta_fetch_lignes_brutes_periode($date_debut, $date_fin, $admin_id, $q);
    $canaux = caisse_compta_canaux_tri();
    $par = [];
    foreach ($canaux as $c) {
        $par[$c] = ['total' => 0.0, 'nb' => 0];
    }
    $total_ttc = 0.0;
    foreach ($rows as $row) {
        $total_ttc += (float) ($row['montant_total'] ?? 0);
        foreach ($canaux as $c) {
            $m = caisse_compta_montant_vente_canal($row, $c);
            if ($m >= 0.005) {
                $par[$c]['total'] += $m;
                $par[$c]['nb']++;
            }
        }
    }
    foreach ($canaux as $c) {
        $par[$c]['total'] = round($par[$c]['total'], 2);
    }

    return [
        'total_ttc' => round($total_ttc, 2),
        'nb' => count($rows),
        'par_canal' => $par,
    ];
}

/**
 * Somme TTC des tickets caisse payés sur la période (même règle que caisse_compta_get_ventes_filtrees).
 */
function caisse_compta_somme_ttc_entre_dates($date_debut, $date_fin)
{
    global $db;
    if (!caisse_tables_exist()) {
        return 0.0;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date_debut) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date_fin)) {
        return 0.0;
    }
    try {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(montant_total), 0) AS s
            FROM caisse_ventes v
            WHERE DATE(COALESCE(v.date_encaissement, v.date_vente)) >= :d1
            AND DATE(COALESCE(v.date_encaissement, v.date_vente)) <= :d2
            AND (v.statut = 'paye' OR v.statut IS NULL)
        ");
        $stmt->execute(['d1' => $date_debut, 'd2' => $date_fin]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (float) ($row['s'] ?? 0) : 0.0;
    } catch (PDOException $e) {
        error_log('[caisse_compta_somme_ttc_entre_dates] ' . $e->getMessage());

        return 0.0;
    }
}

/**
 * Totaux globaux et par canal (liste de tickets déjà filtrée — préférer caisse_compta_agreger_canaux_periode pour la synthèse complète).
 *
 * @return array{total_ttc:float, nb:int, par_canal:array<string,array{nb:int,total:float}>}
 */
function caisse_compta_calculer_totaux(array $ventes)
{
    $total_ttc = 0.0;
    $nb = count($ventes);
    $canaux = caisse_compta_canaux_tri();
    $par_canal = [];
    foreach ($canaux as $c) {
        $par_canal[$c] = ['nb' => 0, 'total' => 0.0];
    }

    foreach ($ventes as $row) {
        $total_ttc += (float) ($row['montant_total'] ?? 0);
        foreach ($canaux as $c) {
            $m = caisse_compta_montant_vente_canal($row, $c);
            if ($m >= 0.005) {
                $par_canal[$c]['nb']++;
                $par_canal[$c]['total'] += $m;
            }
        }
    }

    foreach ($canaux as $c) {
        $par_canal[$c]['total'] = round($par_canal[$c]['total'], 2);
    }

    return [
        'total_ttc' => round($total_ttc, 2),
        'nb' => $nb,
        'par_canal' => $par_canal,
        'par_mode' => [],
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

    $modes_ok = array_merge(caisse_modes_paiement_valides(), ['mobile_money']);
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

/**
 * Date Y-m-d à partir de jour, mois, année (saisie européenne : jour / mois / année).
 *
 * @return string|null
 */
function caisse_compta_date_from_jma($jour, $mois, $annee)
{
    $j = (int) $jour;
    $m = (int) $mois;
    $a = (int) $annee;
    if ($a < 2000 || $a > 2100 || $m < 1 || $m > 12 || $j < 1 || $j > 31) {
        return null;
    }
    if (!checkdate($m, $j, $a)) {
        return null;
    }

    return sprintf('%04d-%02d-%02d', $a, $m, $j);
}

/**
 * @return array{j:int,m:int,a:int}
 */
function caisse_compta_split_ymd($ymd)
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $ymd, $x)) {
        $t = getdate();

        return ['j' => (int) $t['mday'], 'm' => (int) $t['mon'], 'a' => (int) $t['year']];
    }

    return ['j' => (int) $x[3], 'm' => (int) $x[2], 'a' => (int) $x[1]];
}
