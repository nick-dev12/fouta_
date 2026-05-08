<?php
/**
 * Dépenses comptables (HT / TVA)
 * Programmation procédurale uniquement
 */
require_once __DIR__ . '/../conn/conn.php';

/**
 * @return bool
 */
function depenses_tables_ok() {
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT 1 FROM depenses LIMIT 1');
        $db->query('SELECT 1 FROM categories_depenses LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * Catégories par ordre alphabétique
 * @return array<int, array<string, mixed>>
 */
function get_categories_depenses() {
    global $db;
    if (!depenses_tables_ok()) {
        return [];
    }
    try {
        $stmt = $db->query('SELECT id, nom, type_tva FROM categories_depenses ORDER BY nom ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Insère des catégories par défaut si la table est vide
 */
function depenses_seed_categories_if_needed() {
    global $db;
    if (!depenses_tables_ok()) {
        return;
    }
    try {
        $n = (int) $db->query('SELECT COUNT(*) FROM categories_depenses')->fetchColumn();
        if ($n > 0) {
            return;
        }
        $ins = $db->prepare('INSERT INTO categories_depenses (nom, type_tva) VALUES (:nom, :tv)');
        $defs = [
            ['Loyer & charges', 'mixte'],
            ['Fournitures & matériel', 'mixte'],
            ['Transport & logistique', 'mixte'],
            ['Services extérieurs', 'mixte'],
            ['Autres charges', 'mixte'],
        ];
        foreach ($defs as $d) {
            $ins->execute(['nom' => $d[0], 'tv' => $d[1]]);
        }
    } catch (PDOException $e) {
        error_log('[depenses_seed_categories_if_needed] ' . $e->getMessage());
    }
}

/**
 * Calcule l'intervalle [début, fin] (Y-m-d) pour les filtres dépenses.
 *
 * @param string $periode jour|semaine|plage
 * @param string $ref_date date pivot (jour ou semaine la contenant)
 */
function depenses_compute_date_range($periode, $ref_date, $plage_debut = '', $plage_fin = '')
{
    $periode = in_array($periode, ['jour', 'semaine', 'plage'], true) ? $periode : 'jour';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ref_date)) {
        $ref_date = date('Y-m-d');
    }
    $p = explode('-', $ref_date);
    if (count($p) !== 3 || !checkdate((int) $p[1], (int) $p[2], (int) $p[0])) {
        $ref_date = date('Y-m-d');
    }

    if ($periode === 'plage') {
        $db = preg_match('/^\d{4}-\d{2}-\d{2}$/', $plage_debut) ? $plage_debut : date('Y-m-d');
        $df = preg_match('/^\d{4}-\d{2}-\d{2}$/', $plage_fin) ? $plage_fin : date('Y-m-d');
        $pb = explode('-', $db);
        $pf = explode('-', $df);
        if (count($pb) !== 3 || !checkdate((int) $pb[1], (int) $pb[2], (int) $pb[0])) {
            $db = date('Y-m-d');
        }
        if (count($pf) !== 3 || !checkdate((int) $pf[1], (int) $pf[2], (int) $pf[0])) {
            $df = date('Y-m-d');
        }
        if (strcmp($db, $df) > 0) {
            $t = $db;
            $db = $df;
            $df = $t;
        }
        return [$db, $df];
    }
    if ($periode === 'jour') {
        return [$ref_date, $ref_date];
    }
    $dt = new DateTimeImmutable($ref_date);
    $n = (int) $dt->format('N');
    $monday = $dt->modify('-' . ($n - 1) . ' days');
    $sunday = $monday->modify('+6 days');
    return [$monday->format('Y-m-d'), $sunday->format('Y-m-d')];
}

/**
 * Libellé court pour l'interface (période filtrée).
 */
function depenses_libelle_periode_filtre($periode, $date_debut, $date_fin)
{
    $periode = in_array($periode, ['jour', 'semaine', 'plage'], true) ? $periode : 'jour';
    $d1 = $date_debut;
    $d2 = $date_fin;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d2)) {
        return '';
    }
    $fmt = function ($ymd) {
        $ts = strtotime($ymd);
        return $ts ? date('d/m/Y', $ts) : $ymd;
    };
    if ($periode === 'jour') {
        return 'Jour : ' . $fmt($d1);
    }
    if ($periode === 'semaine') {
        return 'Semaine du ' . $fmt($d1) . ' au ' . $fmt($d2);
    }
    return 'Du ' . $fmt($d1) . ' au ' . $fmt($d2);
}

/**
 * @param array<string, mixed> $post
 * @param int $admin_id
 * @return array{success:bool, message?:string}
 */
function process_depense_ajout($post, $admin_id) {
    global $db;
    if (!depenses_tables_ok()) {
        return ['success' => false, 'message' => 'Tables dépenses absentes.'];
    }
    $libelle = isset($post['libelle']) ? trim((string) $post['libelle']) : '';
    $date_dep = isset($post['date_depense']) ? trim((string) $post['date_depense']) : '';
    $type_dep = isset($post['type_depense']) ? trim((string) $post['type_depense']) : '';
    $categorie_id = isset($post['categorie_id']) ? (int) $post['categorie_id'] : 0;
    $montant_ht_raw = isset($post['montant_ht']) ? str_replace(',', '.', trim((string) $post['montant_ht'])) : '0';
    $taux_tva_raw = isset($post['taux_tva']) ? str_replace(',', '.', trim((string) $post['taux_tva'])) : '';
    $notes = isset($post['notes']) ? trim((string) $post['notes']) : '';

    if ($libelle === '' || mb_strlen($libelle) > 255) {
        return ['success' => false, 'message' => 'Libellé obligatoire (255 caractères max).'];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_dep)) {
        return ['success' => false, 'message' => 'Date invalide.'];
    }
    $dp = explode('-', $date_dep);
    if (!checkdate((int) $dp[1], (int) $dp[2], (int) $dp[0])) {
        return ['success' => false, 'message' => 'Date invalide.'];
    }
    if (!in_array($type_dep, ['sans_tva', 'avec_tva'], true)) {
        return ['success' => false, 'message' => 'Type de dépense invalide.'];
    }
    $montant_ht = (float) $montant_ht_raw;
    if ($montant_ht <= 0 || $montant_ht > 999999999.99) {
        return ['success' => false, 'message' => 'Montant HT doit être positif.'];
    }

    $taux_tva = null;
    $montant_tva = null;
    $montant_ttc = null;

    if ($type_dep === 'sans_tva') {
        $montant_ttc = round($montant_ht, 2);
    } else {
        $taux_tva = $taux_tva_raw !== '' ? (float) $taux_tva_raw : 20.0;
        if ($taux_tva < 0 || $taux_tva > 100) {
            return ['success' => false, 'message' => 'Taux TVA entre 0 et 100 %.'];
        }
        $montant_tva = round($montant_ht * ($taux_tva / 100.0), 2);
        $montant_ttc = round($montant_ht + $montant_tva, 2);
    }

    $cat_bind = $categorie_id > 0 ? $categorie_id : null;
    if ($cat_bind !== null) {
        $st = $db->prepare('SELECT id FROM categories_depenses WHERE id = :id LIMIT 1');
        $st->execute(['id' => $cat_bind]);
        if (!$st->fetch()) {
            $cat_bind = null;
        }
    }

    $admin_id = (int) $admin_id;
    $aid = $admin_id > 0 ? $admin_id : null;

    try {
        $stmt = $db->prepare('
            INSERT INTO depenses (
                categorie_id, type_depense, libelle, montant_ht, taux_tva, montant_tva, montant_ttc,
                date_depense, notes, admin_createur_id
            ) VALUES (
                :categorie_id, :type_depense, :libelle, :montant_ht, :taux_tva, :montant_tva, :montant_ttc,
                :date_depense, :notes, :admin_id
            )
        ');
        $stmt->execute([
            'categorie_id' => $cat_bind,
            'type_depense' => $type_dep,
            'libelle' => $libelle,
            'montant_ht' => round($montant_ht, 2),
            'taux_tva' => $taux_tva,
            'montant_tva' => $montant_tva,
            'montant_ttc' => $montant_ttc,
            'date_depense' => $date_dep,
            'notes' => $notes !== '' ? $notes : null,
            'admin_id' => $aid,
        ]);
        return ['success' => true];
    } catch (PDOException $e) {
        error_log('[process_depense_ajout] ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement.'];
    }
}

/**
 * @param array{
 *   date_debut?:string,
 *   date_fin?:string,
 *   categorie_id?:int,
 *   type_depense?:string,
 *   q?:string,
 *   admin_createur_id?:int
 * } $filtres
 * @return array<int, array<string, mixed>>
 */
function get_depenses_filtrees($filtres) {
    global $db;
    if (!depenses_tables_ok()) {
        return [];
    }
    $date_debut = isset($filtres['date_debut']) ? trim((string) $filtres['date_debut']) : '';
    $date_fin = isset($filtres['date_fin']) ? trim((string) $filtres['date_fin']) : '';
    $categorie_id = isset($filtres['categorie_id']) ? (int) $filtres['categorie_id'] : 0;
    $type_depense = isset($filtres['type_depense']) ? trim((string) $filtres['type_depense']) : '';
    $q = isset($filtres['q']) ? trim((string) $filtres['q']) : '';
    $filtre_createur = isset($filtres['admin_createur_id']) ? (int) $filtres['admin_createur_id'] : 0;

    $sql = '
        SELECT d.*, c.nom AS categorie_nom,
            a.nom AS createur_admin_nom, a.prenom AS createur_admin_prenom
        FROM depenses d
        LEFT JOIN categories_depenses c ON d.categorie_id = c.id
        LEFT JOIN admin a ON d.admin_createur_id = a.id
        WHERE 1=1
    ';
    $params = [];

    if ($date_debut !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_debut)) {
        $sql .= ' AND d.date_depense >= :d_debut';
        $params['d_debut'] = $date_debut;
    }
    if ($date_fin !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_fin)) {
        $sql .= ' AND d.date_depense <= :d_fin';
        $params['d_fin'] = $date_fin;
    }
    if ($categorie_id > 0) {
        $sql .= ' AND d.categorie_id = :cat';
        $params['cat'] = $categorie_id;
    }
    if ($type_depense !== '' && in_array($type_depense, ['sans_tva', 'avec_tva'], true)) {
        $sql .= ' AND d.type_depense = :tdep';
        $params['tdep'] = $type_depense;
    }
    if ($q !== '') {
        $sql .= ' AND d.libelle LIKE :q';
        $params['q'] = '%' . $q . '%';
    }
    if ($filtre_createur > 0) {
        $sql .= ' AND d.admin_createur_id = :acid';
        $params['acid'] = $filtre_createur;
    }

    $sql .= ' ORDER BY d.date_depense DESC, d.id DESC';
    $limit_export = isset($filtres['limit']) ? $filtres['limit'] : 500;
    if ($limit_export === null) {
        $limit_export = 500;
    }
    if ($limit_export === 'all' || $limit_export === -1) {
        // export CSV : pas de plafond (plancher de sécurité côté reprise)
    } else {
        $lim = max(1, min(50000, (int) $limit_export));
        $sql .= ' LIMIT ' . $lim;
    }

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[get_depenses_filtrees] ' . $e->getMessage());
        return [];
    }
}

/**
 * Totaux dépenses sur une plage de dates (requête agrégée).
 *
 * @return array{nb:int, sum_ht:float, sum_tva:float, sum_ttc:float}
 */
function depenses_sommes_agregees_periode($date_debut, $date_fin)
{
    global $db;
    $out = ['nb' => 0, 'sum_ht' => 0.0, 'sum_tva' => 0.0, 'sum_ttc' => 0.0];
    if (!depenses_tables_ok()) {
        return $out;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date_debut) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date_fin)) {
        return $out;
    }
    try {
        $stmt = $db->prepare('
            SELECT COUNT(*) AS nb,
                COALESCE(SUM(montant_ht), 0) AS sum_ht,
                COALESCE(SUM(montant_tva), 0) AS sum_tva,
                COALESCE(SUM(montant_ttc), 0) AS sum_ttc
            FROM depenses
            WHERE date_depense >= :d1 AND date_depense <= :d2
        ');
        $stmt->execute(['d1' => $date_debut, 'd2' => $date_fin]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $out;
        }
        return [
            'nb' => (int) ($row['nb'] ?? 0),
            'sum_ht' => (float) ($row['sum_ht'] ?? 0),
            'sum_tva' => (float) ($row['sum_tva'] ?? 0),
            'sum_ttc' => (float) ($row['sum_ttc'] ?? 0),
        ];
    } catch (PDOException $e) {
        error_log('[depenses_sommes_agregees_periode] ' . $e->getMessage());
        return $out;
    }
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array{nb:int, sum_ht:float, sum_tva:float, sum_ttc:float}
 */
function depenses_calculer_totaux($rows) {
    $sum_ht = 0.0;
    $sum_tva = 0.0;
    $sum_ttc = 0.0;
    foreach ($rows as $r) {
        $sum_ht += (float) ($r['montant_ht'] ?? 0);
        $sum_tva += (float) ($r['montant_tva'] ?? 0);
        $sum_ttc += (float) ($r['montant_ttc'] ?? 0);
    }
    return [
        'nb' => count($rows),
        'sum_ht' => $sum_ht,
        'sum_tva' => $sum_tva,
        'sum_ttc' => $sum_ttc,
    ];
}
