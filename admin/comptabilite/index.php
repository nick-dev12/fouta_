<?php
/**
 * Espace Comptabilité — hub à onglets (ventes, dépenses, BL / factures HT, caisse, devis payés)
 */
session_start();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_comptabilite()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_factures_mensuelles.php';
require_once __DIR__ . '/../../models/model_commandes_admin.php';
require_once __DIR__ . '/../../models/model_bl.php';
require_once __DIR__ . '/../../models/model_depenses.php';
require_once __DIR__ . '/../../models/model_caisse_compta.php';
require_once __DIR__ . '/../../models/model_factures_devis.php';
require_once __DIR__ . '/../../models/model_bons_retour.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$depenses_ok = depenses_tables_ok();

$d_periode = isset($_GET['d_periode']) ? trim((string) $_GET['d_periode']) : 'jour';
if (!in_array($d_periode, ['jour', 'semaine', 'plage'], true)) {
    $d_periode = 'jour';
}
$d_today = getdate();
$d_rj = isset($_GET['d_rj']) ? (int) $_GET['d_rj'] : (int) $d_today['mday'];
$d_rm = isset($_GET['d_rm']) ? (int) $_GET['d_rm'] : (int) $d_today['mon'];
$d_ra = isset($_GET['d_ra']) ? (int) $_GET['d_ra'] : (int) $d_today['year'];
$d_p1j = isset($_GET['d_p1j']) ? (int) $_GET['d_p1j'] : (int) $d_today['mday'];
$d_p1m = isset($_GET['d_p1m']) ? (int) $_GET['d_p1m'] : (int) $d_today['mon'];
$d_p1a = isset($_GET['d_p1a']) ? (int) $_GET['d_p1a'] : (int) $d_today['year'];
$d_p2j = isset($_GET['d_p2j']) ? (int) $_GET['d_p2j'] : (int) $d_today['mday'];
$d_p2m = isset($_GET['d_p2m']) ? (int) $_GET['d_p2m'] : (int) $d_today['mon'];
$d_p2a = isset($_GET['d_p2a']) ? (int) $_GET['d_p2a'] : (int) $d_today['year'];

$legacy_d_ref = isset($_GET['d_ref']) ? trim((string) $_GET['d_ref']) : '';
if ($legacy_d_ref !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $legacy_d_ref) && !isset($_GET['d_rj'])) {
    $drp = explode('-', $legacy_d_ref);
    if (count($drp) === 3 && checkdate((int) $drp[1], (int) $drp[2], (int) $drp[0])) {
        $d_ra = (int) $drp[0];
        $d_rm = (int) $drp[1];
        $d_rj = (int) $drp[2];
    }
}
$legacy_d_d1 = isset($_GET['d_date_debut']) ? trim((string) $_GET['d_date_debut']) : '';
if ($legacy_d_d1 !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $legacy_d_d1) && !isset($_GET['d_p1j'])) {
    $dx1 = caisse_compta_split_ymd($legacy_d_d1);
    $d_p1j = $dx1['j'];
    $d_p1m = $dx1['m'];
    $d_p1a = $dx1['a'];
}
$legacy_d_d2 = isset($_GET['d_date_fin']) ? trim((string) $_GET['d_date_fin']) : '';
if ($legacy_d_d2 !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $legacy_d_d2) && !isset($_GET['d_p2j'])) {
    $dx2 = caisse_compta_split_ymd($legacy_d_d2);
    $d_p2j = $dx2['j'];
    $d_p2m = $dx2['m'];
    $d_p2a = $dx2['a'];
}

$d_annee_min = (int) date('Y') - 5;
$d_annee_max = (int) date('Y') + 1;

$d_ref = caisse_compta_date_from_jma($d_rj, $d_rm, $d_ra);
if ($d_ref === null) {
    $d_ref = date('Y-m-d');
}
$d_anchor = caisse_compta_split_ymd($d_ref);
$d_rj = $d_anchor['j'];
$d_rm = $d_anchor['m'];
$d_ra = $d_anchor['a'];

$d_pl_deb = caisse_compta_date_from_jma($d_p1j, $d_p1m, $d_p1a);
if ($d_pl_deb === null) {
    $d_pl_deb = date('Y-m-d');
}
$d_pl_fin = caisse_compta_date_from_jma($d_p2j, $d_p2m, $d_p2a);
if ($d_pl_fin === null) {
    $d_pl_fin = date('Y-m-d');
}

$d_range = depenses_compute_date_range($d_periode, $d_ref, $d_pl_deb, $d_pl_fin);
$d_date_debut = $d_range[0];
$d_date_fin = $d_range[1];

if ($d_periode === 'plage') {
    $dp1 = caisse_compta_split_ymd($d_date_debut);
    $d_p1j = $dp1['j'];
    $d_p1m = $dp1['m'];
    $d_p1a = $dp1['a'];
    $dp2 = caisse_compta_split_ymd($d_date_fin);
    $d_p2j = $dp2['j'];
    $d_p2m = $dp2['m'];
    $d_p2a = $dp2['a'];
}

$d_depenses_periode_label = depenses_libelle_periode_filtre($d_periode, $d_date_debut, $d_date_fin);

$d_categorie = isset($_GET['d_categorie']) ? (int) $_GET['d_categorie'] : 0;
$d_type_dep = isset($_GET['d_type']) && in_array($_GET['d_type'], ['sans_tva', 'avec_tva', ''], true) ? $_GET['d_type'] : '';
$d_q = isset($_GET['d_q']) ? trim((string) $_GET['d_q']) : '';

$categories_dep = [];
$depenses_liste = [];
$totaux_dep = ['nb' => 0, 'sum_ht' => 0.0, 'sum_tva' => 0.0, 'sum_ttc' => 0.0];
if ($depenses_ok) {
    depenses_seed_categories_if_needed();
    $categories_dep = get_categories_depenses();
    $depenses_liste = get_depenses_filtrees([
        'date_debut' => $d_date_debut,
        'date_fin' => $d_date_fin,
        'categorie_id' => $d_categorie,
        'type_depense' => $d_type_dep,
        'q' => $d_q,
    ]);
    $totaux_dep = depenses_calculer_totaux($depenses_liste);
}

$c_periode = isset($_GET['c_periode']) ? trim((string) $_GET['c_periode']) : 'jour';
if (!in_array($c_periode, ['jour', 'semaine', 'plage'], true)) {
    $c_periode = 'jour';
}
$c_today = getdate();
$c_aj = isset($_GET['c_aj']) ? (int) $_GET['c_aj'] : (int) $c_today['mday'];
$c_am = isset($_GET['c_am']) ? (int) $_GET['c_am'] : (int) $c_today['mon'];
$c_aa = isset($_GET['c_aa']) ? (int) $_GET['c_aa'] : (int) $c_today['year'];
$c_p1j = isset($_GET['c_p1j']) ? (int) $_GET['c_p1j'] : (int) $c_today['mday'];
$c_p1m = isset($_GET['c_p1m']) ? (int) $_GET['c_p1m'] : (int) $c_today['mon'];
$c_p1a = isset($_GET['c_p1a']) ? (int) $_GET['c_p1a'] : (int) $c_today['year'];
$c_p2j = isset($_GET['c_p2j']) ? (int) $_GET['c_p2j'] : (int) $c_today['mday'];
$c_p2m = isset($_GET['c_p2m']) ? (int) $_GET['c_p2m'] : (int) $c_today['mon'];
$c_p2a = isset($_GET['c_p2a']) ? (int) $_GET['c_p2a'] : (int) $c_today['year'];

$c_canal_raw = isset($_GET['c_canal']) ? trim((string) $_GET['c_canal']) : '';
$c_canaux_list = function_exists('caisse_compta_canaux_tri') ? caisse_compta_canaux_tri() : [];
$c_canal = ($c_canal_raw === '' || in_array($c_canal_raw, $c_canaux_list, true)) ? $c_canal_raw : '';
$c_admin = isset($_GET['c_admin']) ? (int) $_GET['c_admin'] : 0;
$c_q = isset($_GET['c_q']) ? trim((string) $_GET['c_q']) : '';

$c_ref = caisse_compta_date_from_jma($c_aj, $c_am, $c_aa);
if ($c_ref === null) {
    $c_ref = date('Y-m-d');
}
$c_pl_deb = caisse_compta_date_from_jma($c_p1j, $c_p1m, $c_p1a);
if ($c_pl_deb === null) {
    $c_pl_deb = date('Y-m-d');
}
$c_pl_fin = caisse_compta_date_from_jma($c_p2j, $c_p2m, $c_p2a);
if ($c_pl_fin === null) {
    $c_pl_fin = date('Y-m-d');
}

$c_range = depenses_compute_date_range($c_periode, $c_ref, $c_pl_deb, $c_pl_fin);
$c_date_debut = $c_range[0];
$c_date_fin = $c_range[1];
$c_caisse_periode_label = depenses_libelle_periode_filtre($c_periode, $c_date_debut, $c_date_fin);

if ($c_periode === 'plage') {
    $p = caisse_compta_split_ymd($c_date_debut);
    $c_p1j = $p['j'];
    $c_p1m = $p['m'];
    $c_p1a = $p['a'];
    $p2 = caisse_compta_split_ymd($c_date_fin);
    $c_p2j = $p2['j'];
    $c_p2m = $p2['m'];
    $c_p2a = $p2['a'];
} else {
    if ($c_periode === 'jour') {
        $p = caisse_compta_split_ymd($c_date_debut);
    } else {
        $p = caisse_compta_split_ymd($c_ref);
    }
    $c_aj = $p['j'];
    $c_am = $p['m'];
    $c_aa = $p['a'];
}

$caisse_ok = function_exists('caisse_tables_exist') && caisse_tables_exist();
$caisse_admins_filtre = [];
$caisse_ventes_liste = [];
$caisse_totaux = ['total_ttc' => 0.0, 'nb' => 0, 'par_canal' => []];
$caisse_agrege_canaux = ['total_ttc' => 0.0, 'nb' => 0, 'par_canal' => []];
if ($caisse_ok) {
    $caisse_admins_filtre = caisse_compta_liste_admins_actifs();
    $caisse_agrege_canaux = caisse_compta_agreger_canaux_periode($c_date_debut, $c_date_fin, $c_admin, $c_q);
    $caisse_totaux = [
        'total_ttc' => $caisse_agrege_canaux['total_ttc'],
        'nb' => $caisse_agrege_canaux['nb'],
        'par_canal' => $caisse_agrege_canaux['par_canal'],
    ];
    $caisse_ventes_liste = caisse_compta_get_ventes_filtrees([
        'date_debut' => $c_date_debut,
        'date_fin' => $c_date_fin,
        'canal' => $c_canal,
        'admin_id' => $c_admin,
        'q' => $c_q,
    ]);
}

$compta_caisse_filter_qs = ['tab' => 'caisse', 'c_periode' => $c_periode];
if ($c_periode === 'plage') {
    $compta_caisse_filter_qs['c_p1j'] = $c_p1j;
    $compta_caisse_filter_qs['c_p1m'] = $c_p1m;
    $compta_caisse_filter_qs['c_p1a'] = $c_p1a;
    $compta_caisse_filter_qs['c_p2j'] = $c_p2j;
    $compta_caisse_filter_qs['c_p2m'] = $c_p2m;
    $compta_caisse_filter_qs['c_p2a'] = $c_p2a;
} else {
    $compta_caisse_filter_qs['c_aj'] = $c_aj;
    $compta_caisse_filter_qs['c_am'] = $c_am;
    $compta_caisse_filter_qs['c_aa'] = $c_aa;
}
if ($c_admin > 0) {
    $compta_caisse_filter_qs['c_admin'] = $c_admin;
}
if ($c_q !== '') {
    $compta_caisse_filter_qs['c_q'] = $c_q;
}

$fm_ok = factures_mensuelles_table_ok();
$bl_tables_ok = function_exists('bl_tables_available') && bl_tables_available();

$bl_periode = isset($_GET['bl_periode']) ? trim((string) $_GET['bl_periode']) : date('Y-m');
if (!preg_match('/^(\d{4})-(\d{2})$/', $bl_periode, $bl_periode_m)) {
    $bl_periode = date('Y-m');
    preg_match('/^(\d{4})-(\d{2})$/', $bl_periode, $bl_periode_m);
}
$bl_sel_annee = (int) ($bl_periode_m[1] ?? (int) date('Y'));
$bl_sel_mois = (int) ($bl_periode_m[2] ?? (int) date('n'));
if ($bl_sel_mois < 1 || $bl_sel_mois > 12) {
    $bl_sel_annee = (int) date('Y');
    $bl_sel_mois = (int) date('n');
    $bl_periode = sprintf('%04d-%02d', $bl_sel_annee, $bl_sel_mois);
}

$mois_choices = $bl_tables_ok ? get_mois_distincts_avec_bl() : [];
$cur_period_val = date('Y-m');
$has_current_in_list = false;
foreach ($mois_choices as $mc) {
    if (($mc['value'] ?? '') === $cur_period_val) {
        $has_current_in_list = true;
        break;
    }
}
if (!$has_current_in_list && $bl_tables_ok) {
    $mois_noms_cur = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    $cp = explode('-', $cur_period_val);
    array_unshift($mois_choices, [
        'value' => $cur_period_val,
        'label' => $mois_noms_cur[(int) ($cp[1] ?? 1)] . ' ' . ($cp[0] ?? ''),
        'annee' => (int) ($cp[0] ?? 0),
        'mois' => (int) ($cp[1] ?? 1),
    ]);
}
$seen_periods = [];
$mois_choices_dedup = [];
foreach ($mois_choices as $mc) {
    $v = $mc['value'] ?? '';
    if ($v === '' || isset($seen_periods[$v])) {
        continue;
    }
    $seen_periods[$v] = true;
    $mois_choices_dedup[] = $mc;
}
usort($mois_choices_dedup, function ($a, $b) {
    return strcmp($b['value'] ?? '', $a['value'] ?? '');
});
$mois_choices = $mois_choices_dedup;

$has_sel_period = false;
foreach ($mois_choices as $mc) {
    if (($mc['value'] ?? '') === $bl_periode) {
        $has_sel_period = true;
        break;
    }
}
if ($bl_tables_ok && !$has_sel_period && preg_match('/^(\d{4})-(\d{2})$/', $bl_periode, $pxm)) {
    $mn_add = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    $mois_choices[] = [
        'value' => $bl_periode,
        'label' => $mn_add[(int) $pxm[2]] . ' ' . $pxm[1],
        'annee' => (int) $pxm[1],
        'mois' => (int) $pxm[2],
    ];
    usort($mois_choices, function ($a, $b) {
        return strcmp($b['value'] ?? '', $a['value'] ?? '');
    });
}

$stats_bl_mois = $bl_tables_ok
    ? get_stats_bl_compta_mois($bl_sel_annee, $bl_sel_mois)
    : ['nb_bl' => 0, 'nb_clients' => 0, 'somme_bl_ht' => 0.0, 'nb_valide' => 0];
$stats_fm_mois = $fm_ok
    ? get_somme_et_nb_factures_mensuelles_mois($bl_sel_annee, $bl_sel_mois)
    : ['somme_ht' => 0.0, 'nb_factures' => 0];
$bl_clients_list_compta = $bl_tables_ok ? get_clients_b2b_avec_bl() : [];

$tab_valid = ['ventes', 'depenses', 'bl', 'caisse', 'devis_payes', 'bons_retour'];
$active_tab = isset($_GET['tab']) && in_array($_GET['tab'], $tab_valid, true) ? $_GET['tab'] : 'ventes';

$br_tables_ok_compta = function_exists('br_retour_tables_available') && br_retour_tables_available();
$br_list_compta = $br_tables_ok_compta ? br_get_all_with_bl_client() : [];
$br_clients_list_compta = $br_tables_ok_compta ? get_clients_b2b_avec_bons_retour() : [];
$br_kpi_nb = count($br_list_compta);
$br_kpi_nb_clients = count($br_clients_list_compta);
$br_kpi_total_ht = 0.0;
foreach ($br_list_compta as $br_row) {
    $br_kpi_total_ht += (float) ($br_row['total_ht_retour'] ?? 0);
}

$factures_devis_payees_list = [];
if (function_exists('get_factures_devis_payees_avec_devis')) {
    $factures_devis_payees_list = get_factures_devis_payees_avec_devis();
}
$nb_devis_payes = count($factures_devis_payees_list);

$is_admin_role = in_array(($_SESSION['admin_role'] ?? ''), ['admin', 'informaticien', 'developpeur'], true);

/* Filtre période — onglet Ventes (commandes vendues = livrée ou payée, selon date de commande) */
$v_periode = isset($_GET['v_periode']) ? trim((string) $_GET['v_periode']) : 'jour';
if (!in_array($v_periode, ['jour', 'plage', 'mois', 'annee'], true)) {
    $v_periode = 'jour';
}
$v_annee = (int) date('Y');
$v_mois = (int) date('n');
$v_jour = (int) date('j');
$v_date_debut = isset($_GET['v_date_debut']) ? trim((string) $_GET['v_date_debut']) : '';
$v_date_fin = isset($_GET['v_date_fin']) ? trim((string) $_GET['v_date_fin']) : '';

if ($v_periode === 'mois') {
    $v_annee = isset($_GET['v_annee_mois']) ? (int) $_GET['v_annee_mois'] : (int) date('Y');
    $v_mois = isset($_GET['v_mois']) ? (int) $_GET['v_mois'] : (int) date('n');
} elseif ($v_periode === 'annee') {
    $v_annee = isset($_GET['v_annee']) ? (int) $_GET['v_annee'] : (int) date('Y');
} elseif ($v_periode === 'jour' && !empty($_GET['v_date_jour'])) {
    $vp = explode('-', (string) $_GET['v_date_jour']);
    if (count($vp) === 3 && checkdate((int) $vp[1], (int) $vp[2], (int) $vp[0])) {
        $v_annee = (int) $vp[0];
        $v_mois = (int) $vp[1];
        $v_jour = (int) $vp[2];
    }
}

if ($v_annee < 2000 || $v_annee > 2100) {
    $v_annee = (int) date('Y');
}
if ($v_mois < 1 || $v_mois > 12) {
    $v_mois = (int) date('n');
}
$max_j_m = ($v_annee > 0 && $v_mois >= 1 && $v_mois <= 12)
    ? (int) date('t', mktime(0, 0, 0, $v_mois, 1, $v_annee))
    : 31;
if ($v_jour < 1 || $v_jour > $max_j_m) {
    $v_jour = min((int) date('j'), $max_j_m);
}

$v_date_debut_ok = $v_date_debut !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v_date_debut) && checkdate(
    (int) substr($v_date_debut, 5, 2),
    (int) substr($v_date_debut, 8, 2),
    (int) substr($v_date_debut, 0, 4)
) ? $v_date_debut : '';
$v_date_fin_ok = $v_date_fin !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v_date_fin) && checkdate(
    (int) substr($v_date_fin, 5, 2),
    (int) substr($v_date_fin, 8, 2),
    (int) substr($v_date_fin, 0, 4)
) ? $v_date_fin : '';

$ventes_filtre_actif = isset($_GET['v_applique']) && $_GET['v_applique'] === '1';

if ($ventes_filtre_actif) {
    $commandes_ventes_liste = get_commandes_by_periode(
        $v_periode,
        $v_annee,
        $v_mois,
        $v_date_debut_ok !== '' ? $v_date_debut_ok : null,
        $v_date_fin_ok !== '' ? $v_date_fin_ok : null,
        $v_jour,
        true
    );
    $stats_ventes_affiche = get_stats_ventes_commandes_vendues($commandes_ventes_liste);
    $mois_fr_long_v = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $libelle_periode_ventes = '';
    switch ($v_periode) {
        case 'jour':
            $libelle_periode_ventes = date('d/m/Y', strtotime(sprintf('%04d-%02d-%02d', $v_annee, $v_mois, $v_jour)));
            break;
        case 'plage':
            if ($v_date_debut_ok && $v_date_fin_ok) {
                $libelle_periode_ventes = date('d/m/Y', strtotime($v_date_debut_ok)) . ' – ' . date('d/m/Y', strtotime($v_date_fin_ok));
            } elseif ($v_date_debut_ok) {
                $libelle_periode_ventes = 'À partir du ' . date('d/m/Y', strtotime($v_date_debut_ok));
            } elseif ($v_date_fin_ok) {
                $libelle_periode_ventes = 'Jusqu’au ' . date('d/m/Y', strtotime($v_date_fin_ok));
            } else {
                $libelle_periode_ventes = 'Aujourd’hui (date de commande)';
            }
            break;
        case 'mois':
            $libelle_periode_ventes = ucfirst($mois_fr_long_v[$v_mois] ?? '') . ' ' . $v_annee;
            break;
        case 'annee':
            $libelle_periode_ventes = 'Année ' . $v_annee;
            break;
        default:
            $libelle_periode_ventes = '';
    }
    $ventes_liste_titre_suffix = $libelle_periode_ventes;
} else {
    $stats_ventes_affiche = get_stats_commandes_vendues_globales();
    $commandes_ventes_liste = get_all_commandes_vendues();
    $libelle_periode_ventes = '';
    $ventes_liste_titre_suffix = 'Vue globale — toutes les dates';
}

$tab_ventes_active = $active_tab === 'ventes';
$tab_depenses_active = $active_tab === 'depenses';
$tab_bl_active = $active_tab === 'bl';
$tab_caisse_active = $active_tab === 'caisse';
$tab_devis_payes_active = $active_tab === 'devis_payes';
$tab_bons_retour_active = $active_tab === 'bons_retour';

/* Synthèse hub — cartes gains / dépenses / bénéfice (saisie dates en jour / mois / année) */
$h_periode = isset($_GET['h_periode']) ? trim((string) $_GET['h_periode']) : 'jour';
if (!in_array($h_periode, ['jour', 'semaine', 'plage'], true)) {
    $h_periode = 'jour';
}
$h_today = getdate();
$h_rj = isset($_GET['h_rj']) ? (int) $_GET['h_rj'] : (int) $h_today['mday'];
$h_rm = isset($_GET['h_rm']) ? (int) $_GET['h_rm'] : (int) $h_today['mon'];
$h_ra = isset($_GET['h_ra']) ? (int) $_GET['h_ra'] : (int) $h_today['year'];
$h_p1j = isset($_GET['h_p1j']) ? (int) $_GET['h_p1j'] : (int) $h_today['mday'];
$h_p1m = isset($_GET['h_p1m']) ? (int) $_GET['h_p1m'] : (int) $h_today['mon'];
$h_p1a = isset($_GET['h_p1a']) ? (int) $_GET['h_p1a'] : (int) $h_today['year'];
$h_p2j = isset($_GET['h_p2j']) ? (int) $_GET['h_p2j'] : (int) $h_today['mday'];
$h_p2m = isset($_GET['h_p2m']) ? (int) $_GET['h_p2m'] : (int) $h_today['mon'];
$h_p2a = isset($_GET['h_p2a']) ? (int) $_GET['h_p2a'] : (int) $h_today['year'];

$legacy_h_ref = isset($_GET['h_ref']) ? trim((string) $_GET['h_ref']) : '';
if ($legacy_h_ref !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $legacy_h_ref) && !isset($_GET['h_rj'])) {
    $hrp = explode('-', $legacy_h_ref);
    if (count($hrp) === 3 && checkdate((int) $hrp[1], (int) $hrp[2], (int) $hrp[0])) {
        $h_ra = (int) $hrp[0];
        $h_rm = (int) $hrp[1];
        $h_rj = (int) $hrp[2];
    }
}
$legacy_h_d1 = isset($_GET['h_date_debut']) ? trim((string) $_GET['h_date_debut']) : '';
if ($legacy_h_d1 !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $legacy_h_d1) && !isset($_GET['h_p1j'])) {
    $hx1 = caisse_compta_split_ymd($legacy_h_d1);
    $h_p1j = $hx1['j'];
    $h_p1m = $hx1['m'];
    $h_p1a = $hx1['a'];
}
$legacy_h_d2 = isset($_GET['h_date_fin']) ? trim((string) $_GET['h_date_fin']) : '';
if ($legacy_h_d2 !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $legacy_h_d2) && !isset($_GET['h_p2j'])) {
    $hx2 = caisse_compta_split_ymd($legacy_h_d2);
    $h_p2j = $hx2['j'];
    $h_p2m = $hx2['m'];
    $h_p2a = $hx2['a'];
}

$h_annee_min = (int) date('Y') - 5;
$h_annee_max = (int) date('Y') + 1;

$h_ref = caisse_compta_date_from_jma($h_rj, $h_rm, $h_ra);
if ($h_ref === null) {
    $h_ref = date('Y-m-d');
}
$p_anchor = caisse_compta_split_ymd($h_ref);
$h_rj = $p_anchor['j'];
$h_rm = $p_anchor['m'];
$h_ra = $p_anchor['a'];

$h_pl_deb = caisse_compta_date_from_jma($h_p1j, $h_p1m, $h_p1a);
if ($h_pl_deb === null) {
    $h_pl_deb = date('Y-m-d');
}
$h_pl_fin = caisse_compta_date_from_jma($h_p2j, $h_p2m, $h_p2a);
if ($h_pl_fin === null) {
    $h_pl_fin = date('Y-m-d');
}

$h_range = depenses_compute_date_range($h_periode, $h_ref, $h_pl_deb, $h_pl_fin);
$h_date_debut = $h_range[0];
$h_date_fin = $h_range[1];

if ($h_periode === 'plage') {
    $hp1 = caisse_compta_split_ymd($h_date_debut);
    $h_p1j = $hp1['j'];
    $h_p1m = $hp1['m'];
    $h_p1a = $hp1['a'];
    $hp2 = caisse_compta_split_ymd($h_date_fin);
    $h_p2j = $hp2['j'];
    $h_p2m = $hp2['m'];
    $h_p2a = $hp2['a'];
}

$h_synthese_label = depenses_libelle_periode_filtre($h_periode, $h_date_debut, $h_date_fin);

$h_depenses_agg = $depenses_ok ? depenses_sommes_agregees_periode($h_date_debut, $h_date_fin) : ['sum_ttc' => 0.0];
$h_depenses_ttc = (float) ($h_depenses_agg['sum_ttc'] ?? 0);
$h_ca_web = commandes_ca_vendues_somme_entre_dates($h_date_debut, $h_date_fin);
$h_caisse_ttc = $caisse_ok ? caisse_compta_somme_ttc_entre_dates($h_date_debut, $h_date_fin) : 0.0;
$h_gains_total = $h_ca_web + $h_caisse_ttc;
$h_benefice = $h_gains_total - $h_depenses_ttc;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comptabilité — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
    <?php fpl_css_link('compta-depenses.css'); ?>
    <?php fpl_css_link('compta-bl.css'); ?>
    <?php fpl_css_link('compta-caisse.css'); ?>
    <?php fpl_css_link('compta-bilan.css'); ?>
    <?php fpl_css_link('admin-devis-compta-pages.css'); ?>
    <?php fpl_css_link('admin-comptabilite-index.css'); ?>
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="page-compta-admin">
    <div class="content-header dashboard-hero page-compta-hero">
        <div class="dashboard-hero-text">
            <p class="dashboard-eyebrow">Finance &amp; suivi</p>
            <h1><i class="fas fa-calculator" aria-hidden="true"></i> Comptabilité</h1>
        </div>
    </div>

    <div class="compta-synthese-hub" aria-label="Synthèse gains et charges">
        <form method="get" action="index.php" id="compta-hub-synthese-form" class="compta-ventes-filter compta-synthese-filter">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($active_tab, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="compta-ventes-filter__row">
                <label for="compta-h-periode" class="compta-ventes-filter__label">Vue synthèse</label>
                <div class="compta-ventes-filter__controls">
                    <select name="h_periode" id="compta-h-periode" class="compta-ventes-filter__select">
                        <option value="jour" <?php echo $h_periode === 'jour' ? 'selected' : ''; ?>>Un jour</option>
                        <option value="semaine" <?php echo $h_periode === 'semaine' ? 'selected' : ''; ?>>Une semaine (lun.–dim.)</option>
                        <option value="plage" <?php echo $h_periode === 'plage' ? 'selected' : ''; ?>>Période (du … au …)</option>
                    </select>
                    <button type="submit" class="compta-ventes-filter__btn"><i class="fas fa-filter" aria-hidden="true"></i> Actualiser</button>
                    <a href="index.php?tab=<?php echo urlencode($active_tab); ?>" class="compta-ventes-filter__reset">Aujourd’hui</a>
                </div>
            </div>
            <div id="compta-wrap-h-jour" class="compta-ventes-filter__panel compta-ventes-filter__fields compta-hub-filter__jma-block <?php echo $h_periode === 'jour' ? '' : 'is-hidden'; ?>" title="Ordre : jour / mois / année">
                <div class="compta-hub-filter__date-line">
                    <span class="compta-ventes-filter__sublabel">Date</span>
                    <div class="compta-hub-jma-inline" role="group" aria-label="Date (jour, mois, année)">
                        <select name="h_rj" id="h_rj_jour" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel" aria-label="Jour (1–31)">
                            <?php for ($hd = 1; $hd <= 31; $hd++): ?>
                                <option value="<?php echo $hd; ?>" <?php echo (int) $h_rj === $hd ? 'selected' : ''; ?>><?php echo $hd; ?></option>
                            <?php endfor; ?>
                        </select>
                        <span class="compta-hub-jma-sep" aria-hidden="true">/</span>
                        <select name="h_rm" id="h_rm_jour" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel compta-hub-jma-sel--mois" aria-label="Mois (1–12)">
                            <?php for ($hm = 1; $hm <= 12; $hm++): ?>
                                <option value="<?php echo $hm; ?>" <?php echo (int) $h_rm === $hm ? 'selected' : ''; ?>><?php echo str_pad((string) $hm, 2, '0', STR_PAD_LEFT); ?></option>
                            <?php endfor; ?>
                        </select>
                        <span class="compta-hub-jma-sep" aria-hidden="true">/</span>
                        <select name="h_ra" id="h_ra_jour" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel compta-hub-jma-sel--y" aria-label="Année">
                            <?php for ($hy = $h_annee_max; $hy >= $h_annee_min; $hy--): ?>
                                <option value="<?php echo $hy; ?>" <?php echo (int) $h_ra === $hy ? 'selected' : ''; ?>><?php echo $hy; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div id="compta-wrap-h-semaine" class="compta-ventes-filter__panel compta-ventes-filter__fields compta-hub-filter__jma-block <?php echo $h_periode === 'semaine' ? '' : 'is-hidden'; ?>" title="Ordre : jour / mois / année">
                <div class="compta-hub-filter__date-line">
                    <span class="compta-ventes-filter__sublabel">Semaine contenant le</span>
                    <div class="compta-hub-jma-inline" role="group" aria-label="Date de référence (jour, mois, année)">
                        <select name="h_rj" id="h_rj_sem" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel" aria-label="Jour (1–31)">
                            <?php for ($hd = 1; $hd <= 31; $hd++): ?>
                                <option value="<?php echo $hd; ?>" <?php echo (int) $h_rj === $hd ? 'selected' : ''; ?>><?php echo $hd; ?></option>
                            <?php endfor; ?>
                        </select>
                        <span class="compta-hub-jma-sep" aria-hidden="true">/</span>
                        <select name="h_rm" id="h_rm_sem" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel compta-hub-jma-sel--mois" aria-label="Mois (1–12)">
                            <?php for ($hm = 1; $hm <= 12; $hm++): ?>
                                <option value="<?php echo $hm; ?>" <?php echo (int) $h_rm === $hm ? 'selected' : ''; ?>><?php echo str_pad((string) $hm, 2, '0', STR_PAD_LEFT); ?></option>
                            <?php endfor; ?>
                        </select>
                        <span class="compta-hub-jma-sep" aria-hidden="true">/</span>
                        <select name="h_ra" id="h_ra_sem" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel compta-hub-jma-sel--y" aria-label="Année">
                            <?php for ($hy = $h_annee_max; $hy >= $h_annee_min; $hy--): ?>
                                <option value="<?php echo $hy; ?>" <?php echo (int) $h_ra === $hy ? 'selected' : ''; ?>><?php echo $hy; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div id="compta-wrap-h-plage" class="compta-ventes-filter__panel compta-ventes-filter__fields compta-ventes-filter__fields--plage <?php echo $h_periode === 'plage' ? '' : 'is-hidden'; ?>" title="Ordre : jour / mois / année">
                <div>
                    <span class="compta-ventes-filter__sublabel">Du</span>
                    <div class="compta-hub-jma-inline" role="group" aria-label="Date de début">
                        <select name="h_p1j" id="h_p1j" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel" aria-label="Jour du début">
                            <?php for ($hd = 1; $hd <= 31; $hd++): ?>
                                <option value="<?php echo $hd; ?>" <?php echo (int) $h_p1j === $hd ? 'selected' : ''; ?>><?php echo $hd; ?></option>
                            <?php endfor; ?>
                        </select>
                        <span class="compta-hub-jma-sep" aria-hidden="true">/</span>
                        <select name="h_p1m" id="h_p1m" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel compta-hub-jma-sel--mois" aria-label="Mois du début">
                            <?php for ($hm = 1; $hm <= 12; $hm++): ?>
                                <option value="<?php echo $hm; ?>" <?php echo (int) $h_p1m === $hm ? 'selected' : ''; ?>><?php echo str_pad((string) $hm, 2, '0', STR_PAD_LEFT); ?></option>
                            <?php endfor; ?>
                        </select>
                        <span class="compta-hub-jma-sep" aria-hidden="true">/</span>
                        <select name="h_p1a" id="h_p1a" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel compta-hub-jma-sel--y" aria-label="Année du début">
                            <?php for ($hy = $h_annee_max; $hy >= $h_annee_min; $hy--): ?>
                                <option value="<?php echo $hy; ?>" <?php echo (int) $h_p1a === $hy ? 'selected' : ''; ?>><?php echo $hy; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <span class="compta-ventes-filter__sublabel">Au</span>
                    <div class="compta-hub-jma-inline" role="group" aria-label="Date de fin">
                        <select name="h_p2j" id="h_p2j" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel" aria-label="Jour de fin">
                            <?php for ($hd = 1; $hd <= 31; $hd++): ?>
                                <option value="<?php echo $hd; ?>" <?php echo (int) $h_p2j === $hd ? 'selected' : ''; ?>><?php echo $hd; ?></option>
                            <?php endfor; ?>
                        </select>
                        <span class="compta-hub-jma-sep" aria-hidden="true">/</span>
                        <select name="h_p2m" id="h_p2m" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel compta-hub-jma-sel--mois" aria-label="Mois de fin">
                            <?php for ($hm = 1; $hm <= 12; $hm++): ?>
                                <option value="<?php echo $hm; ?>" <?php echo (int) $h_p2m === $hm ? 'selected' : ''; ?>><?php echo str_pad((string) $hm, 2, '0', STR_PAD_LEFT); ?></option>
                            <?php endfor; ?>
                        </select>
                        <span class="compta-hub-jma-sep" aria-hidden="true">/</span>
                        <select name="h_p2a" id="h_p2a" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel compta-hub-jma-sel--y" aria-label="Année de fin">
                            <?php for ($hy = $h_annee_max; $hy >= $h_annee_min; $hy--): ?>
                                <option value="<?php echo $hy; ?>" <?php echo (int) $h_p2a === $hy ? 'selected' : ''; ?>><?php echo $hy; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
        </form>
        <p class="compta-synthese-hub__period"><i class="fas fa-calendar-check" aria-hidden="true"></i> <?php echo htmlspecialchars($h_synthese_label); ?> · gains = commandes web (livrées / payées) + caisse TTC ; dépenses = charges TTC saisies.</p>
        <div class="compta-synthese-cards">
            <article class="compta-synthese-card compta-synthese-card--depenses">
                <span class="compta-synthese-card__ic" aria-hidden="true"><i class="fas fa-wallet"></i></span>
                <div class="compta-synthese-card__body">
                    <span class="compta-synthese-card__label">Dépenses (TTC)</span>
                    <span class="compta-synthese-card__value"><?php echo $depenses_ok ? number_format($h_depenses_ttc, 0, ',', ' ') : '—'; ?> <small>FCFA</small></span>
                </div>
            </article>
            <article class="compta-synthese-card compta-synthese-card--gains">
                <span class="compta-synthese-card__ic" aria-hidden="true"><i class="fas fa-sack-dollar"></i></span>
                <div class="compta-synthese-card__body">
                    <span class="compta-synthese-card__label">Gains (revenus TTC)</span>
                    <span class="compta-synthese-card__value"><?php echo number_format($h_gains_total, 0, ',', ' '); ?> <small>FCFA</small></span>
                    <span class="compta-synthese-card__detail">Web <?php echo number_format($h_ca_web, 0, ',', ' '); ?> + Caisse <?php echo number_format($h_caisse_ttc, 0, ',', ' '); ?></span>
                </div>
            </article>
            <article class="compta-synthese-card compta-synthese-card--benefice<?php echo $h_benefice < 0 ? ' compta-synthese-card--negative' : ''; ?>">
                <span class="compta-synthese-card__ic" aria-hidden="true"><i class="fas fa-scale-balanced"></i></span>
                <div class="compta-synthese-card__body">
                    <span class="compta-synthese-card__label">Bénéfice estimé</span>
                    <span class="compta-synthese-card__value"><?php echo number_format($h_benefice, 0, ',', ' '); ?> <small>FCFA</small></span>
                    <span class="compta-synthese-card__detail">Gains − dépenses (TTC)</span>
                </div>
            </article>
        </div>
    </div>

    <section class="content-section compta-page page-compta-section" aria-label="Espace comptabilité">
        <div class="compta-tabs-wrap">
            <div class="compta-tabs" role="tablist" aria-label="Sections comptabilité">
                <button type="button" class="compta-tab compta-tab--ventes <?php echo $tab_ventes_active ? 'is-active' : ''; ?>" id="compta-tab-ventes" role="tab" aria-selected="<?php echo $tab_ventes_active ? 'true' : 'false'; ?>" aria-controls="compta-panel-ventes" data-compta-tab="ventes">
                    <span class="compta-tab__ic" aria-hidden="true"><i class="fas fa-shopping-cart"></i></span>
                    <span class="compta-tab__txt">
                        <span class="compta-tab__label">Ventes &amp; commandes</span>
                        <span class="compta-tab__hint">Produits vendus, file commandes</span>
                    </span>
                </button>
                <button type="button" class="compta-tab compta-tab--depenses <?php echo $tab_depenses_active ? 'is-active' : ''; ?>" id="compta-tab-depenses" role="tab" aria-selected="<?php echo $tab_depenses_active ? 'true' : 'false'; ?>" aria-controls="compta-panel-depenses" data-compta-tab="depenses">
                    <span class="compta-tab__ic" aria-hidden="true"><i class="fas fa-wallet"></i></span>
                    <span class="compta-tab__txt">
                        <span class="compta-tab__label">Dépenses</span>
                        <span class="compta-tab__hint">Charges, TVA, suivi</span>
                    </span>
                </button>
                <button type="button" class="compta-tab compta-tab--bl <?php echo $tab_bl_active ? 'is-active' : ''; ?>" id="compta-tab-bl" role="tab" aria-selected="<?php echo $tab_bl_active ? 'true' : 'false'; ?>" aria-controls="compta-panel-bl" data-compta-tab="bl">
                    <span class="compta-tab__ic" aria-hidden="true"><i class="fas fa-truck-loading"></i></span>
                    <span class="compta-tab__txt">
                        <span class="compta-tab__label">Bons de livraison</span>
                        <span class="compta-tab__hint">Factures HT, BL B2B</span>
                    </span>
                </button>
                <button type="button" class="compta-tab compta-tab--caisse <?php echo $tab_caisse_active ? 'is-active' : ''; ?>" id="compta-tab-caisse" role="tab" aria-selected="<?php echo $tab_caisse_active ? 'true' : 'false'; ?>" aria-controls="compta-panel-caisse" data-compta-tab="caisse">
                    <span class="compta-tab__ic" aria-hidden="true"><i class="fas fa-cash-register"></i></span>
                    <span class="compta-tab__txt">
                        <span class="compta-tab__label">Caisse magasin</span>
                        <span class="compta-tab__hint">Tickets TTC, filtres</span>
                    </span>
                </button>
                <button type="button" class="compta-tab compta-tab--devis-payes <?php echo $tab_devis_payes_active ? 'is-active' : ''; ?>" id="compta-tab-devis-payes" role="tab" aria-selected="<?php echo $tab_devis_payes_active ? 'true' : 'false'; ?>" aria-controls="compta-panel-devis-payes" data-compta-tab="devis_payes">
                    <span class="compta-tab__ic" aria-hidden="true"><i class="fas fa-file-invoice-dollar"></i></span>
                    <span class="compta-tab__txt">
                        <span class="compta-tab__label">Devis payés</span>
                        <span class="compta-tab__hint">Factures devis réglées (FPL)</span>
                    </span>
                </button>
                <button type="button" class="compta-tab compta-tab--bons-retour <?php echo $tab_bons_retour_active ? 'is-active' : ''; ?>" id="compta-tab-bons-retour" role="tab" aria-selected="<?php echo $tab_bons_retour_active ? 'true' : 'false'; ?>" aria-controls="compta-panel-bons-retour" data-compta-tab="bons_retour" <?php echo !$br_tables_ok_compta ? 'disabled title="Tables bons de retour absentes"' : ''; ?>>
                    <span class="compta-tab__ic" aria-hidden="true"><i class="fas fa-undo"></i></span>
                    <span class="compta-tab__txt">
                        <span class="compta-tab__label">Bons de retour</span>
                        <span class="compta-tab__hint">Retours B2B, montants HT</span>
                    </span>
                </button>
            </div>
        </div>

        <div id="compta-panel-ventes" class="compta-panel <?php echo $tab_ventes_active ? 'is-active' : ''; ?>" role="tabpanel" aria-labelledby="compta-tab-ventes" <?php echo $tab_ventes_active ? '' : 'hidden'; ?> data-compta-panel="ventes">
            <div class="compta-hero compta-hero--ventes">
                <div class="compta-hero__copy">
                    <h2 class="compta-hero__title">Ventes &amp; commandes e-commerce</h2>
                    <div class="compta-hero__actions">
                        <?php if (!admin_is_restricted_admin_account()): ?>
                        <a href="../commandes/index.php" class="compta-btn compta-btn--primary"><i class="fas fa-list" aria-hidden="true"></i> Ouvrir les commandes</a>
                        <?php endif; ?>
                        <?php if ($is_admin_role): ?>
                            <a href="../commandes/historique-ventes.php" class="compta-btn compta-btn--secondary"><i class="fas fa-chart-line" aria-hidden="true"></i> Historique des ventes</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <form method="get" action="index.php" id="compta-ventes-form" class="compta-ventes-filter" aria-label="Filtrer les ventes par période">
                <input type="hidden" name="tab" value="ventes">
                <div class="compta-ventes-filter__row">
                    <label for="compta-v-periode" class="compta-ventes-filter__label">Type de filtre (date de commande)</label>
                    <div class="compta-ventes-filter__controls">
                        <select name="v_periode" id="compta-v-periode" class="compta-ventes-filter__select">
                            <option value="jour" <?php echo $v_periode === 'jour' ? 'selected' : ''; ?>>Un jour</option>
                            <option value="plage" <?php echo $v_periode === 'plage' ? 'selected' : ''; ?>>Période (du … au …)</option>
                            <option value="mois" <?php echo $v_periode === 'mois' ? 'selected' : ''; ?>>Un mois calendaire</option>
                            <option value="annee" <?php echo $v_periode === 'annee' ? 'selected' : ''; ?>>Une année</option>
                        </select>
                        <button type="submit" name="v_applique" value="1" class="compta-ventes-filter__btn"><i class="fas fa-filter" aria-hidden="true"></i> Afficher</button>
                        <a href="index.php?tab=ventes" class="compta-ventes-filter__reset">Vue globale</a>
                    </div>
                </div>

                <div id="compta-wrap-v-jour" class="compta-ventes-filter__panel compta-ventes-filter__fields <?php echo $v_periode === 'jour' ? '' : 'is-hidden'; ?>">
                    <label for="v_date_jour" class="compta-ventes-filter__sublabel">Date</label>
                    <input type="date" name="v_date_jour" id="v_date_jour" class="compta-ventes-filter__date"
                        value="<?php echo htmlspecialchars(sprintf('%04d-%02d-%02d', $v_annee, $v_mois, $v_jour)); ?>">
                </div>

                <div id="compta-wrap-v-plage" class="compta-ventes-filter__panel compta-ventes-filter__fields compta-ventes-filter__fields--plage <?php echo $v_periode === 'plage' ? '' : 'is-hidden'; ?>">
                    <div>
                        <label for="v_date_debut" class="compta-ventes-filter__sublabel">Du</label>
                        <input type="date" name="v_date_debut" id="v_date_debut" class="compta-ventes-filter__date"
                            value="<?php echo htmlspecialchars($v_date_debut_ok !== '' ? $v_date_debut_ok : date('Y-m-d')); ?>">
                    </div>
                    <div>
                        <label for="v_date_fin" class="compta-ventes-filter__sublabel">Au</label>
                        <input type="date" name="v_date_fin" id="v_date_fin" class="compta-ventes-filter__date"
                            value="<?php echo htmlspecialchars($v_date_fin_ok !== '' ? $v_date_fin_ok : date('Y-m-d')); ?>">
                    </div>
                </div>

                <div id="compta-wrap-v-mois" class="compta-ventes-filter__panel compta-ventes-filter__fields compta-ventes-filter__fields--inline <?php echo $v_periode === 'mois' ? '' : 'is-hidden'; ?>">
                    <div>
                        <label for="v_annee_mois" class="compta-ventes-filter__sublabel">Année</label>
                        <select name="v_annee_mois" id="v_annee_mois" class="compta-ventes-filter__select compta-ventes-filter__select--sm">
                            <?php for ($ay = (int) date('Y'); $ay >= (int) date('Y') - 5; $ay--): ?>
                                <option value="<?php echo $ay; ?>" <?php echo $v_annee === $ay ? 'selected' : ''; ?>><?php echo $ay; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label for="v_mois" class="compta-ventes-filter__sublabel">Mois</label>
                        <select name="v_mois" id="v_mois" class="compta-ventes-filter__select compta-ventes-filter__select--md">
                            <?php
                            $mois_labels = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
                            for ($m = 1; $m <= 12; $m++):
                                ?>
                                <option value="<?php echo $m; ?>" <?php echo $v_mois === $m ? 'selected' : ''; ?>><?php echo htmlspecialchars($mois_labels[$m]); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div id="compta-wrap-v-annee" class="compta-ventes-filter__panel compta-ventes-filter__fields <?php echo $v_periode === 'annee' ? '' : 'is-hidden'; ?>">
                    <label for="v_annee_seule" class="compta-ventes-filter__sublabel">Année</label>
                    <select name="v_annee" id="v_annee_seule" class="compta-ventes-filter__select compta-ventes-filter__select--sm">
                        <?php for ($ay = (int) date('Y'); $ay >= (int) date('Y') - 5; $ay--): ?>
                            <option value="<?php echo $ay; ?>" <?php echo $v_annee === $ay ? 'selected' : ''; ?>><?php echo $ay; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </form>

            <div class="compta-stat-grid" aria-label="<?php echo $ventes_filtre_actif ? 'Indicateurs ventes sur la période filtrée' : 'Indicateurs ventes (vue globale)'; ?>">
                <div class="compta-stat-card">
                    <span class="compta-stat-card__label">Commandes vendues<?php echo $ventes_filtre_actif ? '' : ' (global)'; ?></span>
                    <span class="compta-stat-card__value"><?php echo number_format($stats_ventes_affiche['nb'], 0, ',', ' '); ?></span>
                </div>
                <div class="compta-stat-card">
                    <span class="compta-stat-card__label">CA vendu (livrées + payées)<?php echo $ventes_filtre_actif ? '' : ' (global)'; ?></span>
                    <span class="compta-stat-card__value"><?php echo number_format($stats_ventes_affiche['ca_total'], 0, ',', ' '); ?> <small>FCFA</small></span>
                </div>
                <div class="compta-stat-card compta-stat-card--note">
                    <span class="compta-stat-card__label"><?php echo $ventes_filtre_actif ? 'Détail sur la période' : 'Détail (global)'; ?></span>
                    <p class="compta-stat-card__mini">Livrées : <?php echo number_format($stats_ventes_affiche['ca_livree'], 0, ',', ' '); ?> · Payées : <?php echo number_format($stats_ventes_affiche['ca_paye'], 0, ',', ' '); ?> FCFA</p>
                </div>
            </div>

            <h3 class="compta-section-title compta-section-title--ventes-list"><i class="fas fa-receipt" aria-hidden="true"></i> Commandes vendues <span class="compta-section-title__per">(<?php echo htmlspecialchars($ventes_liste_titre_suffix); ?>)</span></h3>

            <?php if (empty($commandes_ventes_liste)): ?>
                <div class="compta-blank compta-blank--tight">
                    <p><?php echo $ventes_filtre_actif
                        ? '<strong>Aucune commande vendue</strong> sur cette période (statuts livrée ou payée, selon la date de commande).'
                        : '<strong>Aucune commande vendue</strong> en base (statuts livrée ou payée).'; ?></p>
                </div>
            <?php else: ?>
                <div class="compta-ventes-grid" role="list" aria-label="Liste des commandes vendues">
                    <?php foreach ($commandes_ventes_liste as $cv):
                        $cid = (int) ($cv['id'] ?? 0);
                        $st = $cv['statut'] ?? '';
                        $st_label = $st === 'paye' ? 'Payée' : ($st === 'livree' ? 'Livrée' : ucfirst(str_replace('_', ' ', $st)));
                        $client_nom = trim(($cv['user_prenom'] ?? '') . ' ' . ($cv['user_nom'] ?? ''));
                        if ($client_nom === '') {
                            $client_nom = '—';
                        }
                        ?>
                    <article class="compta-vente-card" role="listitem">
                        <div class="compta-vente-card__top">
                            <h4 class="compta-vente-card__num"><?php echo htmlspecialchars($cv['numero_commande'] ?? ''); ?></h4>
                            <span class="commande-statut statut-<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($st_label); ?></span>
                        </div>
                        <p class="compta-vente-card__client"><i class="fas fa-user" aria-hidden="true"></i> <?php echo htmlspecialchars($client_nom); ?></p>
                        <p class="compta-vente-card__date"><i class="fas fa-clock" aria-hidden="true"></i> <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($cv['date_commande'] ?? 'now'))); ?></p>
                        <p class="compta-vente-card__mt"><?php echo number_format((float) ($cv['montant_total'] ?? 0), 0, ',', ' '); ?> <span>FCFA</span></p>
                        <a href="../commandes/details.php?id=<?php echo $cid; ?>" class="compta-vente-card__link">Voir la commande <i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                    </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>

        <div id="compta-panel-depenses" class="compta-panel compta-panel--depenses <?php echo $tab_depenses_active ? 'is-active' : ''; ?>" role="tabpanel" aria-labelledby="compta-tab-depenses" <?php echo $tab_depenses_active ? '' : 'hidden'; ?> data-compta-panel="depenses">
            <div class="compta-hero compta-hero--depenses compta-dep-hero compta-dep-hero--premium">
                <div class="compta-hero__copy">
                    <p class="compta-dep-eyebrow">Charges &amp; suivi</p>
                    <h2 class="compta-hero__title" id="compta-dep-hero-title">Dépenses</h2>
                    <p class="compta-hero__lead">Consultation des charges saisies par la <strong>caisse</strong> (montants en <strong>HT</strong> / <strong>TVA</strong>, FCFA). La saisie est réservée au profil caissier (menu <strong>Dépenses caisse</strong>).</p>
                </div>
            </div>

            <?php if (!$depenses_ok): ?>
                <p class="message error compta-dep-msg compta-dep-msg--standalone"><i class="fas fa-database" aria-hidden="true"></i> Tables absentes : exécutez la migration <code>migrations/create_depenses_compta.sql</code> (ou <code>migration_admin_b2b_structure.sql</code>).</p>
            <?php else: ?>
            <div class="compta-dep-body">

                <div class="compta-dep-kpis" aria-label="Synthèse sur la période filtrée">
                    <div class="compta-dep-kpi">
                        <span class="compta-dep-kpi__label">Lignes</span>
                        <span class="compta-dep-kpi__val"><?php echo (int) $totaux_dep['nb']; ?></span>
                    </div>
                    <div class="compta-dep-kpi compta-dep-kpi--ht">
                        <span class="compta-dep-kpi__label">Total HT</span>
                        <span class="compta-dep-kpi__val"><?php echo number_format($totaux_dep['sum_ht'], 0, ',', ' '); ?> <small>FCFA</small></span>
                    </div>
                    <div class="compta-dep-kpi compta-dep-kpi--tva">
                        <span class="compta-dep-kpi__label">TVA</span>
                        <span class="compta-dep-kpi__val"><?php echo number_format($totaux_dep['sum_tva'], 0, ',', ' '); ?> <small>FCFA</small></span>
                    </div>
                    <div class="compta-dep-kpi compta-dep-kpi--ttc">
                        <span class="compta-dep-kpi__label">Total TTC</span>
                        <span class="compta-dep-kpi__val"><?php echo number_format($totaux_dep['sum_ttc'], 0, ',', ' '); ?> <small>FCFA</small></span>
                    </div>
                </div>
                <p class="compta-dep-periode-hint"><i class="fas fa-calendar-alt" aria-hidden="true"></i> <?php echo htmlspecialchars($d_depenses_periode_label); ?></p>

                <form method="get" action="index.php" id="compta-dep-filtres-form" class="compta-ventes-filter compta-dep-periode-form" aria-label="Filtrer les dépenses par période">
                    <input type="hidden" name="tab" value="depenses">
                    <div class="compta-ventes-filter__row">
                        <label for="compta-d-periode" class="compta-ventes-filter__label">Période (date de dépense)</label>
                        <div class="compta-ventes-filter__controls">
                            <select name="d_periode" id="compta-d-periode" class="compta-ventes-filter__select">
                                <option value="jour" <?php echo $d_periode === 'jour' ? 'selected' : ''; ?>>Un jour</option>
                                <option value="semaine" <?php echo $d_periode === 'semaine' ? 'selected' : ''; ?>>Une semaine (lun.–dim.)</option>
                                <option value="plage" <?php echo $d_periode === 'plage' ? 'selected' : ''; ?>>Période (du … au …)</option>
                            </select>
                            <button type="submit" class="compta-ventes-filter__btn"><i class="fas fa-filter" aria-hidden="true"></i> Afficher</button>
                            <a href="index.php?tab=depenses" class="compta-ventes-filter__reset">Aujourd’hui</a>
                        </div>
                    </div>
                    <div id="compta-wrap-d-jour" class="compta-ventes-filter__panel compta-ventes-filter__fields compta-hub-filter__jma-block <?php echo $d_periode === 'jour' ? '' : 'is-hidden'; ?>" title="Ordre : jour / mois / année">
                        <div class="compta-hub-filter__date-line">
                            <span class="compta-ventes-filter__sublabel">Date</span>
                            <div class="compta-hub-jma-inline" role="group" aria-label="Date (jour, mois, année)">
                                <select name="d_rj" id="d_rj_jour" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel" aria-label="Jour (1–31)">
                                    <?php for ($dd = 1; $dd <= 31; $dd++): ?>
                                        <option value="<?php echo $dd; ?>" <?php echo (int) $d_rj === $dd ? 'selected' : ''; ?>><?php echo $dd; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="compta-hub-jma-sep" aria-hidden="true">/</span>
                                <select name="d_rm" id="d_rm_jour" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel compta-hub-jma-sel--mois" aria-label="Mois (1–12)">
                                    <?php for ($dm = 1; $dm <= 12; $dm++): ?>
                                        <option value="<?php echo $dm; ?>" <?php echo (int) $d_rm === $dm ? 'selected' : ''; ?>><?php echo str_pad((string) $dm, 2, '0', STR_PAD_LEFT); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="compta-hub-jma-sep" aria-hidden="true">/</span>
                                <select name="d_ra" id="d_ra_jour" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel compta-hub-jma-sel--y" aria-label="Année">
                                    <?php for ($dy = $d_annee_max; $dy >= $d_annee_min; $dy--): ?>
                                        <option value="<?php echo $dy; ?>" <?php echo (int) $d_ra === $dy ? 'selected' : ''; ?>><?php echo $dy; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div id="compta-wrap-d-semaine" class="compta-ventes-filter__panel compta-ventes-filter__fields compta-hub-filter__jma-block <?php echo $d_periode === 'semaine' ? '' : 'is-hidden'; ?>" title="Ordre : jour / mois / année">
                        <div class="compta-hub-filter__date-line">
                            <span class="compta-ventes-filter__sublabel">Semaine contenant le</span>
                            <div class="compta-hub-jma-inline" role="group" aria-label="Date de référence (jour, mois, année)">
                                <select name="d_rj" id="d_rj_sem" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel" aria-label="Jour (1–31)">
                                    <?php for ($dd = 1; $dd <= 31; $dd++): ?>
                                        <option value="<?php echo $dd; ?>" <?php echo (int) $d_rj === $dd ? 'selected' : ''; ?>><?php echo $dd; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="compta-hub-jma-sep" aria-hidden="true">/</span>
                                <select name="d_rm" id="d_rm_sem" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel compta-hub-jma-sel--mois" aria-label="Mois (1–12)">
                                    <?php for ($dm = 1; $dm <= 12; $dm++): ?>
                                        <option value="<?php echo $dm; ?>" <?php echo (int) $d_rm === $dm ? 'selected' : ''; ?>><?php echo str_pad((string) $dm, 2, '0', STR_PAD_LEFT); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="compta-hub-jma-sep" aria-hidden="true">/</span>
                                <select name="d_ra" id="d_ra_sem" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel compta-hub-jma-sel--y" aria-label="Année">
                                    <?php for ($dy = $d_annee_max; $dy >= $d_annee_min; $dy--): ?>
                                        <option value="<?php echo $dy; ?>" <?php echo (int) $d_ra === $dy ? 'selected' : ''; ?>><?php echo $dy; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div id="compta-wrap-d-plage" class="compta-ventes-filter__panel compta-ventes-filter__fields compta-ventes-filter__fields--plage <?php echo $d_periode === 'plage' ? '' : 'is-hidden'; ?>" title="Ordre : jour / mois / année">
                        <div>
                            <span class="compta-ventes-filter__sublabel">Du</span>
                            <div class="compta-hub-jma-inline" role="group" aria-label="Date de début">
                                <select name="d_p1j" id="d_p1j" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel" aria-label="Jour du début">
                                    <?php for ($dd = 1; $dd <= 31; $dd++): ?>
                                        <option value="<?php echo $dd; ?>" <?php echo (int) $d_p1j === $dd ? 'selected' : ''; ?>><?php echo $dd; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="compta-hub-jma-sep" aria-hidden="true">/</span>
                                <select name="d_p1m" id="d_p1m" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel compta-hub-jma-sel--mois" aria-label="Mois du début">
                                    <?php for ($dm = 1; $dm <= 12; $dm++): ?>
                                        <option value="<?php echo $dm; ?>" <?php echo (int) $d_p1m === $dm ? 'selected' : ''; ?>><?php echo str_pad((string) $dm, 2, '0', STR_PAD_LEFT); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="compta-hub-jma-sep" aria-hidden="true">/</span>
                                <select name="d_p1a" id="d_p1a" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel compta-hub-jma-sel--y" aria-label="Année du début">
                                    <?php for ($dy = $d_annee_max; $dy >= $d_annee_min; $dy--): ?>
                                        <option value="<?php echo $dy; ?>" <?php echo (int) $d_p1a === $dy ? 'selected' : ''; ?>><?php echo $dy; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <span class="compta-ventes-filter__sublabel">Au</span>
                            <div class="compta-hub-jma-inline" role="group" aria-label="Date de fin">
                                <select name="d_p2j" id="d_p2j" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel" aria-label="Jour de fin">
                                    <?php for ($dd = 1; $dd <= 31; $dd++): ?>
                                        <option value="<?php echo $dd; ?>" <?php echo (int) $d_p2j === $dd ? 'selected' : ''; ?>><?php echo $dd; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="compta-hub-jma-sep" aria-hidden="true">/</span>
                                <select name="d_p2m" id="d_p2m" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel compta-hub-jma-sel--mois" aria-label="Mois de fin">
                                    <?php for ($dm = 1; $dm <= 12; $dm++): ?>
                                        <option value="<?php echo $dm; ?>" <?php echo (int) $d_p2m === $dm ? 'selected' : ''; ?>><?php echo str_pad((string) $dm, 2, '0', STR_PAD_LEFT); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="compta-hub-jma-sep" aria-hidden="true">/</span>
                                <select name="d_p2a" id="d_p2a" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-hub-jma-sel compta-hub-jma-sel--y" aria-label="Année de fin">
                                    <?php for ($dy = $d_annee_max; $dy >= $d_annee_min; $dy--): ?>
                                        <option value="<?php echo $dy; ?>" <?php echo (int) $d_p2a === $dy ? 'selected' : ''; ?>><?php echo $dy; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="compta-dep-filtres-detail compta-ventes-filter__panel compta-ventes-filter__fields" style="margin-top:12px;">
                        <div class="compta-dep-filters__field">
                            <label for="d_categorie">Catégorie</label>
                            <select name="d_categorie" id="d_categorie">
                                <option value="0">Toutes</option>
                                <?php foreach ($categories_dep as $cat): ?>
                                    <option value="<?php echo (int) $cat['id']; ?>" <?php echo $d_categorie === (int) $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="compta-dep-filters__field">
                            <label for="d_type">TVA</label>
                            <select name="d_type" id="d_type">
                                <option value="" <?php echo $d_type_dep === '' ? 'selected' : ''; ?>>Tous types</option>
                                <option value="sans_tva" <?php echo $d_type_dep === 'sans_tva' ? 'selected' : ''; ?>>Sans TVA</option>
                                <option value="avec_tva" <?php echo $d_type_dep === 'avec_tva' ? 'selected' : ''; ?>>Avec TVA</option>
                            </select>
                        </div>
                        <div class="compta-dep-filters__field compta-dep-filters__field--full">
                            <label for="d_q">Recherche libellé</label>
                            <input type="search" name="d_q" id="d_q" value="<?php echo htmlspecialchars($d_q); ?>" placeholder="Mot-clé…" autocomplete="off">
                        </div>
                    </div>
                </form>

                <h3 class="compta-section-title compta-dep-list-title"><i class="fas fa-list" aria-hidden="true"></i> Détail des dépenses <span class="compta-section-title__per">(<?php echo htmlspecialchars($d_depenses_periode_label); ?>)</span></h3>

                <?php if (empty($depenses_liste)): ?>
                    <div class="compta-dep-empty">
                        <i class="fas fa-inbox" aria-hidden="true"></i>
                        <p>Aucune dépense pour ces critères.</p>
                    </div>
                <?php else: ?>
                    <div class="compta-dep-list" role="list" aria-label="Liste des dépenses enregistrées">
                        <?php foreach ($depenses_liste as $row):
                            $d_raw = $row['date_depense'] ?? '';
                            $d_ts = $d_raw !== '' ? strtotime($d_raw) : false;
                            $d_iso = ($d_ts !== false) ? date('Y-m-d', $d_ts) : '';
                            $d_fr = ($d_ts !== false) ? date('d/m/Y', $d_ts) : '—';
                            $type_dep = $row['type_depense'] ?? '';
                            $ht = number_format((float) ($row['montant_ht'] ?? 0), 0, ',', ' ');
                            $tva_show = $row['montant_tva'] !== null && (float) $row['montant_tva'] > 0;
                            $tva = $tva_show ? number_format((float) $row['montant_tva'], 0, ',', ' ') : '—';
                            $ttc = number_format((float) ($row['montant_ttc'] ?? 0), 0, ',', ' ');
                            $createur_etiq = trim(($row['createur_admin_prenom'] ?? '') . ' ' . ($row['createur_admin_nom'] ?? ''));
                            if ($createur_etiq === '') {
                                $createur_etiq = '—';
                            }
                            ?>
                        <article class="compta-dep-item" role="listitem">
                            <div class="compta-dep-item__top">
                                <?php if ($d_iso !== ''): ?>
                                <time class="compta-dep-item__date" datetime="<?php echo htmlspecialchars($d_iso); ?>"><?php echo htmlspecialchars($d_fr); ?></time>
                                <?php else: ?>
                                <span class="compta-dep-item__date"><?php echo htmlspecialchars($d_fr); ?></span>
                                <?php endif; ?>
                                <span class="compta-dep-badge compta-dep-badge--<?php echo htmlspecialchars($type_dep); ?>"><?php echo $type_dep === 'avec_tva' ? 'TVA' : 'HT seul'; ?></span>
                            </div>
                            <h4 class="compta-dep-item__title"><?php echo htmlspecialchars($row['libelle'] ?? ''); ?></h4>
                            <p class="compta-dep-item__cat">
                                <i class="fas fa-folder-open" aria-hidden="true"></i>
                                <span class="compta-dep-item__sr">Catégorie : </span>
                                <?php echo htmlspecialchars($row['categorie_nom'] ?? '—'); ?>
                            </p>
                            <p class="compta-dep-item__createur">
                                <i class="fas fa-user-tie" aria-hidden="true"></i>
                                <span class="compta-dep-item__sr">Saisie : </span>
                                <?php echo htmlspecialchars($createur_etiq); ?>
                            </p>
                            <dl class="compta-dep-item__amounts">
                                <div class="compta-dep-item__amt">
                                    <dt>HT</dt>
                                    <dd><span class="compta-dep-item__num"><?php echo $ht; ?></span> <span class="compta-dep-item__cur">FCFA</span></dd>
                                </div>
                                <div class="compta-dep-item__amt">
                                    <dt>TVA</dt>
                                    <dd><span class="compta-dep-item__num<?php echo $tva_show ? '' : ' compta-dep-item__num--muted'; ?>"><?php echo htmlspecialchars($tva); ?></span><?php if ($tva_show): ?> <span class="compta-dep-item__cur">FCFA</span><?php endif; ?></dd>
                                </div>
                                <div class="compta-dep-item__amt compta-dep-item__amt--ttc">
                                    <dt>TTC</dt>
                                    <dd><span class="compta-dep-item__num compta-dep-item__num--ttc"><?php echo $ttc; ?></span> <span class="compta-dep-item__cur">FCFA</span></dd>
                                </div>
                            </dl>
                        </article>
                        <?php endforeach; ?>
                    </div>
                    <p class="compta-dep-footnote"><i class="fas fa-info-circle" aria-hidden="true"></i> Affichage limité aux 500 lignes les plus récentes correspondant aux filtres.</p>
                <?php endif; ?>

            </div>
            <?php endif; ?>
        </div>

        <div id="compta-panel-bl" class="compta-panel compta-panel--bl <?php echo $tab_bl_active ? 'is-active' : ''; ?>" role="tabpanel" aria-labelledby="compta-tab-bl" <?php echo $tab_bl_active ? '' : 'hidden'; ?> data-compta-panel="bl">
            <?php
            $mois_fr_long = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
            $periode_label_long = $mois_fr_long[$bl_sel_mois] . ' ' . $bl_sel_annee;
            ?>
            <div class="compta-hero compta-hero--bl compta-bl-hero compta-bl-hero--premium">
                <div class="compta-hero__copy">
                    <p class="compta-bl-eyebrow">B2B · <?php echo htmlspecialchars($periode_label_long); ?></p>
                    <h2 class="compta-hero__title" id="compta-bl-hero-title">Bons de livraison</h2>
                    <div class="compta-hero__actions compta-bl-hero__actions">
                        <a href="#compta-bl-clients-anchor" class="compta-btn compta-btn--secondary"><i class="fas fa-people-group" aria-hidden="true"></i> Liste clients &amp; BL</a>
                        <a href="bl-factures-archives.php" class="compta-btn compta-btn--primary"><i class="fas fa-list" aria-hidden="true"></i> Liste des factures</a>
                    </div>
                </div>
            </div>

            <div class="compta-bl-body">

            <?php if (!$bl_tables_ok): ?>
                <div class="compta-bl-alerts" role="status">
                    <p class="message error compta-bl-msg compta-bl-msg--standalone"><i class="fas fa-database" aria-hidden="true"></i> Tables BL absentes : exécutez <code>migrations/migration_admin_b2b_structure.sql</code>.</p>
                </div>
            <?php else: ?>
                <h3 class="compta-section-title compta-bl-synth-title"><i class="fas fa-truck-loading" aria-hidden="true"></i> Bons de livraison</h3>
                <form method="get" action="index.php" class="compta-bl-filter" aria-label="Filtrer par mois">
                    <input type="hidden" name="tab" value="bl">
                    <div class="compta-bl-filter__row">
                        <label for="bl_periode" class="compta-bl-filter__label">Période (date du BL)</label>
                        <div class="compta-bl-filter__controls">
                            <select name="bl_periode" id="bl_periode" class="compta-bl-filter__select">
                                <?php foreach ($mois_choices as $mc): ?>
                                    <option value="<?php echo htmlspecialchars($mc['value']); ?>" <?php echo ($mc['value'] === $bl_periode) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($mc['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="compta-bl-filter__btn"><i class="fas fa-filter" aria-hidden="true"></i> Afficher</button>
                        </div>
                    </div>
                </form>

                <div class="compta-bl-kpis" aria-label="Synthèse de la période">
                    <div class="compta-bl-kpi">
                        <span class="compta-bl-kpi__ic" aria-hidden="true"><i class="fas fa-file-invoice-dollar"></i></span>
                        <div class="compta-bl-kpi__body">
                            <span class="compta-bl-kpi__label">Factures HT enregistrées</span>
                            <span class="compta-bl-kpi__value"><?php echo number_format($stats_fm_mois['somme_ht'], 0, ',', ' '); ?> <small>FCFA</small></span>
                        </div>
                    </div>
                    <div class="compta-bl-kpi">
                        <span class="compta-bl-kpi__ic" aria-hidden="true"><i class="fas fa-file-alt"></i></span>
                        <div class="compta-bl-kpi__body">
                            <span class="compta-bl-kpi__label">Bons de livraison (compta)</span>
                            <span class="compta-bl-kpi__value"><?php echo (int) $stats_bl_mois['nb_bl']; ?></span>
                        </div>
                    </div>
                    <div class="compta-bl-kpi">
                        <span class="compta-bl-kpi__ic" aria-hidden="true"><i class="fas fa-users"></i></span>
                        <div class="compta-bl-kpi__body">
                            <span class="compta-bl-kpi__label">Clients distincts</span>
                            <span class="compta-bl-kpi__value"><?php echo (int) $stats_bl_mois['nb_clients']; ?></span>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

            <h3 id="compta-bl-clients-anchor" class="compta-section-title compta-section-title--spaced compta-bl-list-title"><i class="fas fa-people-group" aria-hidden="true"></i> Clients &amp; bons de livraison</h3>

            <?php if ($bl_tables_ok && empty($bl_clients_list_compta)): ?>
                <div class="compta-blank compta-bl-empty">
                    <p><i class="fas fa-people-group" aria-hidden="true"></i> Aucun client B2B avec bon de livraison pour le moment.</p>
                </div>
            <?php elseif ($bl_tables_ok && !empty($bl_clients_list_compta)): ?>
                <?php $bl_nb_contacts_compta = count($bl_clients_list_compta); ?>
                <div class="bl-tab-surface compta-bl-tab-surface">
                    <header class="bl-contacts-hero">
                        <div class="bl-contacts-hero__icon-wrap" aria-hidden="true">
                            <i class="fas fa-people-group"></i>
                        </div>
                        <div class="bl-contacts-hero__copy">
                            <h2 class="bl-contacts-hero__title">Contacts B2B</h2>
                            <p class="bl-contacts-hero__lead compta-bl-contacts-hero__lead">Clients avec bons de livraison — accès à la fiche et aux BL.</p>
                        </div>
                        <div class="bl-contacts-hero__stat" title="Nombre de contacts listés">
                            <span class="bl-contacts-hero__stat-num"><?php echo (int) $bl_nb_contacts_compta; ?></span>
                            <span class="bl-contacts-hero__stat-label">contact<?php echo $bl_nb_contacts_compta > 1 ? 's' : ''; ?></span>
                        </div>
                    </header>
                    <div class="compta-panel-table-wrap compta-bl-clients-table-wrap">
                        <table class="data-table compta-bl-clients-table" aria-labelledby="compta-bl-clients-anchor">
                            <thead>
                                <tr>
                                    <th scope="col" class="compta-bl-clients-table__th compta-bl-clients-table__th--client"><span class="compta-bl-clients-table__th-label"><i class="fas fa-building" aria-hidden="true"></i> Client</span></th>
                                    <th scope="col" class="compta-bl-clients-table__th"><span class="compta-bl-clients-table__th-label"><i class="fas fa-phone-alt" aria-hidden="true"></i> Téléphone</span></th>
                                    <th scope="col" class="compta-bl-clients-table__th"><span class="compta-bl-clients-table__th-label"><i class="fas fa-envelope" aria-hidden="true"></i> Email</span></th>
                                    <th scope="col" class="compta-table-col-num compta-bl-clients-table__th compta-bl-clients-table__th--bl"><span class="compta-bl-clients-table__th-label"><i class="fas fa-truck" aria-hidden="true"></i> BL</span></th>
                                    <th scope="col" class="compta-table-col-actions compta-bl-clients-table__th compta-bl-clients-table__th--actions"><span class="compta-bl-clients-table__th-label"><i class="fas fa-external-link-alt" aria-hidden="true"></i> Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                    <?php foreach ($bl_clients_list_compta as $cl): ?>
                        <?php
                        $cid = (int) $cl['id'];
                        $nb_bl_c = (int) ($cl['nb_bl'] ?? 0);
                        $rs_c = trim($cl['raison_sociale'] ?? '');
                        $tel_raw = trim((string) ($cl['telephone'] ?? ''));
                        $email_raw = trim((string) ($cl['email'] ?? ''));
                        ?>
                                <tr class="compta-bl-clients-table__row">
                                    <td class="compta-bl-clients-table__cell compta-bl-clients-table__cell--client">
                                        <div class="compta-bl-clients-table__client">
                                            <span class="compta-bl-clients-table__company"><?php echo htmlspecialchars($rs_c ?: '—'); ?></span>
                                        </div>
                                    </td>
                                    <td class="compta-bl-clients-table__cell compta-bl-clients-table__cell--meta">
                                        <?php if ($tel_raw !== ''): ?>
                                            <a class="compta-bl-clients-table__link compta-bl-clients-table__link--tel" href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $tel_raw)); ?>"><?php echo htmlspecialchars($tel_raw); ?></a>
                                        <?php else: ?>
                                            <span class="compta-bl-clients-table__empty">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="compta-bl-clients-table__cell compta-bl-clients-table__cell--meta">
                                        <?php if ($email_raw !== ''): ?>
                                            <a class="compta-bl-clients-table__link compta-bl-clients-table__link--mail" href="mailto:<?php echo htmlspecialchars($email_raw); ?>"><?php echo htmlspecialchars($email_raw); ?></a>
                                        <?php else: ?>
                                            <span class="compta-bl-clients-table__empty">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="compta-table-col-num compta-bl-clients-table__cell compta-bl-clients-table__cell--bl">
                                        <span class="compta-bl-clients-table__bl-pill" title="Nombre de bons de livraison"><?php echo (int) $nb_bl_c; ?></span>
                                    </td>
                                    <td class="compta-table-actions compta-bl-clients-table__cell compta-bl-clients-table__cell--cta">
                                        <a class="compta-bl-clients-table__cta" href="bl-fiche-client.php?id=<?php echo $cid; ?>" aria-label="Ouvrir la fiche client, les bons de livraison et la facturation"><span class="compta-bl-clients-table__cta-text">Fiche client</span><i class="fas fa-arrow-right compta-bl-clients-table__cta-ic" aria-hidden="true"></i></a>
                                    </td>
                                </tr>
                    <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            </div><!-- .compta-bl-body -->
        </div>

        <div id="compta-panel-caisse" class="compta-panel compta-panel--caisse <?php echo $tab_caisse_active ? 'is-active' : ''; ?>" role="tabpanel" aria-labelledby="compta-tab-caisse" <?php echo $tab_caisse_active ? '' : 'hidden'; ?> data-compta-panel="caisse">
            <?php
            $caisse_annee_min = (int) date('Y') - 5;
            $caisse_annee_max = (int) date('Y') + 1;
            ?>
            <div class="compta-hero compta-hero--caisse compta-caisse-hero compta-caisse-hero--premium">
                <div class="compta-hero__copy">
                    <p class="compta-caisse-eyebrow">Caisse magasin · TTC · <?php echo htmlspecialchars($c_caisse_periode_label); ?></p>
                    <h2 class="compta-hero__title" id="compta-caisse-hero-title">Caisse magasin</h2>
                </div>
            </div>

            <div class="compta-caisse-body">

            <?php if (!$caisse_ok): ?>
                <div class="compta-caisse-alerts" role="status">
                    <p class="message error compta-caisse-msg compta-caisse-msg--standalone"><i class="fas fa-database" aria-hidden="true"></i> Tables caisse absentes : exécutez <code>migrations/create_caisse_tables.sql</code>, puis enregistrez des ventes depuis la caisse.</p>
                </div>
            <?php else: ?>

                <div class="compta-caisse-kpis" aria-label="Synthèse sur la période filtrée">
                    <div class="compta-caisse-kpi">
                        <span class="compta-caisse-kpi__label">Tickets</span>
                        <span class="compta-caisse-kpi__val"><?php echo (int) $caisse_totaux['nb']; ?></span>
                    </div>
                    <div class="compta-caisse-kpi compta-caisse-kpi--ttc">
                        <span class="compta-caisse-kpi__label">Total TTC</span>
                        <span class="compta-caisse-kpi__val"><?php echo number_format($caisse_totaux['total_ttc'], 0, ',', ' '); ?> <small>FCFA</small></span>
                    </div>
                </div>

                <nav class="compta-caisse-subtabs" aria-label="Filtrer le détail par canal de paiement">
                    <a class="compta-caisse-subtab <?php echo $c_canal === '' ? 'is-active' : ''; ?>" href="index.php?<?php echo htmlspecialchars(http_build_query($compta_caisse_filter_qs)); ?>">Tous</a>
                    <?php foreach ($c_canaux_list as $ck):
                        $qs_canal = array_merge($compta_caisse_filter_qs, ['c_canal' => $ck]);
                        $pch = $caisse_totaux['par_canal'][$ck] ?? ['total' => 0, 'nb' => 0];
                        ?>
                    <a class="compta-caisse-subtab <?php echo $c_canal === $ck ? 'is-active' : ''; ?>"
                        href="index.php?<?php echo htmlspecialchars(http_build_query($qs_canal)); ?>">
                        <span class="compta-caisse-subtab__name"><?php echo htmlspecialchars(caisse_compta_libelle_mode($ck)); ?></span>
                        <span class="compta-caisse-subtab__sum"><?php echo number_format((float) $pch['total'], 0, ',', ' '); ?> FCFA</span>
                    </a>
                    <?php endforeach; ?>
                </nav>

                <?php if (!empty($caisse_totaux['par_canal']) && $caisse_ok): ?>
                <div class="compta-caisse-modes compta-caisse-modes--canal" aria-label="Montants encaissés par canal (paiements mixtes ventilés)">
                    <?php foreach ($caisse_totaux['par_canal'] as $mk => $mv):
                        if ((float) $mv['total'] < 0.005) {
                            continue;
                        }
                        ?>
                    <div class="compta-caisse-mode-chip">
                        <span class="compta-caisse-mode-chip__name"><?php echo htmlspecialchars(caisse_compta_libelle_mode($mk)); ?></span>
                        <span class="compta-caisse-mode-chip__nb"><?php echo (int) $mv['nb']; ?> ligne<?php echo $mv['nb'] > 1 ? 's' : ''; ?></span>
                        <span class="compta-caisse-mode-chip__sum"><?php echo number_format((float) $mv['total'], 0, ',', ' '); ?> FCFA</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="compta-caisse-filter-hub" aria-label="Filtres caisse magasin">
                    <form method="get" action="index.php" class="compta-ventes-filter compta-synthese-filter compta-caisse-compact-filter" id="compta-caisse-filters-form">
                        <input type="hidden" name="tab" value="caisse">
                        <?php if ($c_canal !== ''): ?>
                        <input type="hidden" name="c_canal" value="<?php echo htmlspecialchars($c_canal); ?>">
                        <?php endif; ?>
                        <div class="compta-ventes-filter__row compta-caisse-filter__row-top">
                            <label for="compta-c-periode" class="compta-ventes-filter__label">Période caisse</label>
                            <div class="compta-ventes-filter__controls">
                                <select name="c_periode" id="compta-c-periode" class="compta-ventes-filter__select compta-ventes-filter__select--md">
                                    <option value="jour" <?php echo $c_periode === 'jour' ? 'selected' : ''; ?>>Un jour</option>
                                    <option value="semaine" <?php echo $c_periode === 'semaine' ? 'selected' : ''; ?>>Une semaine (lun.–dim.)</option>
                                    <option value="plage" <?php echo $c_periode === 'plage' ? 'selected' : ''; ?>>Période (du … au …)</option>
                                </select>
                                <button type="submit" class="compta-ventes-filter__btn"><i class="fas fa-filter" aria-hidden="true"></i> Appliquer</button>
                                <a href="index.php?tab=caisse" class="compta-ventes-filter__reset">Réinitialiser</a>
                            </div>
                        </div>

                        <div id="compta-wrap-c-anchor" class="compta-ventes-filter__panel compta-ventes-filter__fields compta-caisse-filter__date-panel <?php echo $c_periode === 'plage' ? 'is-hidden' : ''; ?>" title="Ordre de saisie : jour / mois / année">
                            <div class="compta-caisse-filter__date-line">
                                <span class="compta-ventes-filter__sublabel compta-caisse-filter__date-line-label" id="compta-c-anchor-label"><?php echo $c_periode === 'semaine' ? 'Semaine contenant le' : 'Date'; ?></span>
                                <div class="compta-caisse-jma-inline" role="group" aria-labelledby="compta-c-anchor-label">
                                    <select name="c_aj" id="c_aj" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-caisse-jma-sel" aria-label="Jour (1–31)">
                                        <?php for ($cd = 1; $cd <= 31; $cd++): ?>
                                            <option value="<?php echo $cd; ?>" <?php echo (int) $c_aj === $cd ? 'selected' : ''; ?>><?php echo $cd; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="compta-caisse-jma-sep" aria-hidden="true">/</span>
                                    <select name="c_am" id="c_am" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-caisse-jma-sel compta-caisse-jma-sel--mois" aria-label="Mois (1–12)" title="Mois (ordre jour / mois / année)">
                                        <?php for ($cm = 1; $cm <= 12; $cm++): ?>
                                            <option value="<?php echo $cm; ?>" <?php echo (int) $c_am === $cm ? 'selected' : ''; ?>><?php echo str_pad((string) $cm, 2, '0', STR_PAD_LEFT); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="compta-caisse-jma-sep" aria-hidden="true">/</span>
                                    <select name="c_aa" id="c_aa" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-caisse-jma-sel compta-caisse-jma-sel--y" aria-label="Année">
                                        <?php for ($ca = $caisse_annee_max; $ca >= $caisse_annee_min; $ca--): ?>
                                            <option value="<?php echo $ca; ?>" <?php echo (int) $c_aa === $ca ? 'selected' : ''; ?>><?php echo $ca; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div id="compta-wrap-c-plage" class="compta-ventes-filter__panel compta-ventes-filter__fields compta-ventes-filter__fields--plage compta-caisse-filter__date-panel <?php echo $c_periode === 'plage' ? '' : 'is-hidden'; ?>" title="Ordre : jour / mois / année">
                            <div>
                                <span class="compta-ventes-filter__sublabel">Du</span>
                                <div class="compta-caisse-jma-inline" role="group" aria-label="Date de début">
                                    <select name="c_p1j" id="c_p1j" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-caisse-jma-sel" aria-label="Jour du début">
                                        <?php for ($cd = 1; $cd <= 31; $cd++): ?>
                                            <option value="<?php echo $cd; ?>" <?php echo (int) $c_p1j === $cd ? 'selected' : ''; ?>><?php echo $cd; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="compta-caisse-jma-sep" aria-hidden="true">/</span>
                                    <select name="c_p1m" id="c_p1m" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-caisse-jma-sel compta-caisse-jma-sel--mois" aria-label="Mois du début">
                                        <?php for ($cm = 1; $cm <= 12; $cm++): ?>
                                            <option value="<?php echo $cm; ?>" <?php echo (int) $c_p1m === $cm ? 'selected' : ''; ?>><?php echo str_pad((string) $cm, 2, '0', STR_PAD_LEFT); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="compta-caisse-jma-sep" aria-hidden="true">/</span>
                                    <select name="c_p1a" id="c_p1a" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-caisse-jma-sel compta-caisse-jma-sel--y" aria-label="Année du début">
                                        <?php for ($ca = $caisse_annee_max; $ca >= $caisse_annee_min; $ca--): ?>
                                            <option value="<?php echo $ca; ?>" <?php echo (int) $c_p1a === $ca ? 'selected' : ''; ?>><?php echo $ca; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <span class="compta-ventes-filter__sublabel">Au</span>
                                <div class="compta-caisse-jma-inline" role="group" aria-label="Date de fin">
                                    <select name="c_p2j" id="c_p2j" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-caisse-jma-sel" aria-label="Jour de fin">
                                        <?php for ($cd = 1; $cd <= 31; $cd++): ?>
                                            <option value="<?php echo $cd; ?>" <?php echo (int) $c_p2j === $cd ? 'selected' : ''; ?>><?php echo $cd; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="compta-caisse-jma-sep" aria-hidden="true">/</span>
                                    <select name="c_p2m" id="c_p2m" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-caisse-jma-sel compta-caisse-jma-sel--mois" aria-label="Mois de fin">
                                        <?php for ($cm = 1; $cm <= 12; $cm++): ?>
                                            <option value="<?php echo $cm; ?>" <?php echo (int) $c_p2m === $cm ? 'selected' : ''; ?>><?php echo str_pad((string) $cm, 2, '0', STR_PAD_LEFT); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="compta-caisse-jma-sep" aria-hidden="true">/</span>
                                    <select name="c_p2a" id="c_p2a" class="compta-ventes-filter__select compta-ventes-filter__select--sm compta-caisse-jma-sel compta-caisse-jma-sel--y" aria-label="Année de fin">
                                        <?php for ($ca = $caisse_annee_max; $ca >= $caisse_annee_min; $ca--): ?>
                                            <option value="<?php echo $ca; ?>" <?php echo (int) $c_p2a === $ca ? 'selected' : ''; ?>><?php echo $ca; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="compta-ventes-filter__fields compta-ventes-filter__fields--plage compta-caisse-filter__row-extra">
                            <div>
                                <label for="c_admin" class="compta-ventes-filter__sublabel">Vendeur (ticket)</label>
                                <select name="c_admin" id="c_admin" class="compta-ventes-filter__select compta-ventes-filter__select--md">
                                    <option value="0">Tous</option>
                                    <?php foreach ($caisse_admins_filtre as $adm): ?>
                                        <option value="<?php echo (int) $adm['id']; ?>" <?php echo $c_admin === (int) $adm['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(trim(($adm['prenom'] ?? '') . ' ' . ($adm['nom'] ?? '')) ?: ($adm['email'] ?? '#' . $adm['id'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="compta-caisse-filter__field-q">
                                <label for="c_q" class="compta-ventes-filter__sublabel">Recherche</label>
                                <input type="search" name="c_q" id="c_q" class="compta-ventes-filter__date compta-caisse-filter__q-input" value="<?php echo htmlspecialchars($c_q); ?>" placeholder="N° ticket, note…" autocomplete="off">
                            </div>
                        </div>
                    </form>
                </div>

                <h3 class="compta-section-title compta-caisse-list-title compta-caisse-tickets-title"><i class="fas fa-receipt" aria-hidden="true"></i> Détail des tickets</h3>

                <?php if (empty($caisse_ventes_liste)): ?>
                    <div class="compta-dep-empty compta-caisse-empty compta-caisse-empty--box">
                        <i class="fas fa-inbox" aria-hidden="true"></i>
                        <p>Aucune vente caisse pour ces critères.</p>
                    </div>
                <?php else: ?>
                    <div class="compta-caisse-table-wrap compta-caisse-table-wrap--premium">
                        <table class="compta-caisse-table">
                            <thead>
                                <tr>
                                    <th scope="col">Date / heure</th>
                                    <th scope="col">Ticket</th>
                                    <th scope="col">Caissier</th>
                                    <th scope="col">Paiement</th>
                                    <th scope="col" class="compta-caisse-table__num">TTC</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($caisse_ventes_liste as $cv):
                                    $dt = $cv['date_vente'] ?? '';
                                    $dt_fr = $dt !== '' ? date('d/m/Y H:i', strtotime($dt)) : '—';
                                    $adm_nom = trim(($cv['admin_prenom'] ?? '') . ' ' . ($cv['admin_nom'] ?? ''));
                                    if ($adm_nom === '') {
                                        $adm_nom = '—';
                                    }
                                    $mode_lbl = caisse_compta_libelle_paiement_ticket($cv);
                                    ?>
                                <tr>
                                    <td data-label="Date"><time datetime="<?php echo htmlspecialchars(substr($dt, 0, 19)); ?>"><?php echo htmlspecialchars($dt_fr); ?></time></td>
                                    <td data-label="Ticket"><strong><?php echo htmlspecialchars($cv['numero_ticket'] ?? ''); ?></strong></td>
                                    <td data-label="Caissier"><?php echo htmlspecialchars($adm_nom); ?></td>
                                    <td data-label="Paiement"><span class="compta-caisse-badge"><?php echo htmlspecialchars($mode_lbl); ?></span></td>
                                    <td class="compta-caisse-table__num" data-label="TTC"><?php echo number_format((float) ($cv['montant_total'] ?? 0), 0, ',', ' '); ?> <span class="compta-caisse-cur">FCFA</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="compta-dep-footnote compta-caisse-footnote"><i class="fas fa-info-circle" aria-hidden="true"></i> Affichage limité aux 500 tickets les plus récents correspondant aux filtres. Montants <strong>TTC</strong> tels qu’enregistrés à la caisse.</p>
                <?php endif; ?>

            <?php endif; ?>

            </div><!-- .compta-caisse-body -->
        </div>

        <div id="compta-panel-devis-payes" class="compta-panel compta-panel--devis-payes <?php echo $tab_devis_payes_active ? 'is-active' : ''; ?>" role="tabpanel" aria-labelledby="compta-tab-devis-payes" <?php echo $tab_devis_payes_active ? '' : 'hidden'; ?> data-compta-panel="devis_payes">
            <div class="compta-hero compta-hero--devis-payes">
                <div class="compta-hero__copy">
                    <p class="compta-caisse-eyebrow">Factures devis · comptabilité</p>
                    <h2 class="compta-hero__title" id="compta-devis-payes-hero-title">Devis payés</h2>
                    <p class="compta-hero__lead">Devis dont la facture a été <strong>marquée payée</strong> depuis l’écran facture : référence <strong>FPL</strong>, montants et liens vers le devis et la facture.</p>
                    <p class="compta-hero__lead compta-devis-payes-migration-hint" role="note">
                        <?php if (!function_exists('factures_devis_col_payee_ok') || !factures_devis_col_payee_ok()): ?>
                            <i class="fas fa-database" aria-hidden="true"></i> Colonnes absentes : exécutez <code>migrations/run_add_factures_devis_paiement_fpl.php</code>.
                        <?php else: ?>
                            <span class="compta-devis-payes-count"><strong><?php echo (int) $nb_devis_payes; ?></strong> facturation<?php echo $nb_devis_payes > 1 ? 's' : ''; ?> réglée<?php echo $nb_devis_payes > 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="compta-devis-payes-body">
                <?php if (!function_exists('factures_devis_col_payee_ok') || !factures_devis_col_payee_ok()): ?>
                    <div class="compta-dep-empty compta-devis-payes-empty" role="status">
                        <i class="fas fa-plug" aria-hidden="true"></i>
                        <p>Migrez la table <code>factures_devis</code> pour activer le suivi des paiements.</p>
                    </div>
                <?php elseif (empty($factures_devis_payees_list)): ?>
                    <div class="compta-dep-empty compta-devis-payes-empty" role="status">
                        <i class="fas fa-inbox" aria-hidden="true"></i>
                        <p>Aucune facture de devis marquée payée pour l’instant.</p>
                    </div>
                <?php else: ?>
                    <div class="compta-panel-table-wrap compta-devis-payes-table-wrap">
                        <table class="data-table compta-devis-payes-table" aria-labelledby="compta-devis-payes-hero-title">
                            <thead>
                                <tr>
                                    <th scope="col">Statut</th>
                                    <th scope="col">Référence</th>
                                    <th scope="col">Client</th>
                                    <th scope="col">Devis</th>
                                    <th scope="col">Paiement</th>
                                    <th scope="col">Ancien n°</th>
                                    <th scope="col" class="compta-table-col-num">Montant</th>
                                    <th scope="col" class="compta-table-col-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                        <?php foreach ($factures_devis_payees_list as $fpx):
                            $fid = (int) ($fpx['id'] ?? 0);
                            $did = (int) ($fpx['devis_id'] ?? $fpx['devis_id_ref'] ?? 0);
                            $ref_fpl = (string) ($fpx['numero_reference_fpl'] ?? '');
                            $num_inv = (string) ($fpx['numero_facture'] ?? '');
                            $cli = trim(($fpx['client_prenom'] ?? '') . ' ' . ($fpx['client_nom'] ?? ''));
                            if ($cli === '') {
                                $cli = 'Client';
                            }
                            $nd = (string) ($fpx['numero_devis'] ?? '');
                            $dp = $fpx['date_paiement'] ?? '';
                            $dp_fr = $dp !== '' ? date('d/m/Y H:i', strtotime((string) $dp)) : '—';
                            $mt = (float) ($fpx['montant_total'] ?? 0);
                            $show_ancien = ($ref_fpl !== '' && $num_inv !== '' && strcasecmp($ref_fpl, $num_inv) !== 0);
                            $ref_display = $ref_fpl !== '' ? $ref_fpl : $num_inv;
                            ?>
                                <tr>
                                    <td><span class="compta-devis-payee-card__badge" title="Réglé"><i class="fas fa-check-circle" aria-hidden="true"></i> Payé</span></td>
                                    <td><span class="compta-devis-payee-card__ref"><?php echo htmlspecialchars($ref_display); ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($cli); ?></strong></td>
                                    <td>#<?php echo htmlspecialchars($nd); ?></td>
                                    <td><?php echo htmlspecialchars($dp_fr); ?></td>
                                    <td class="compta-devis-payes-table__muted"><?php echo $show_ancien ? htmlspecialchars($num_inv) : '—'; ?></td>
                                    <td class="compta-table-col-num"><?php echo number_format($mt, 0, ',', ' '); ?> <small>FCFA</small></td>
                                    <td class="compta-table-actions compta-table-actions--split">
                                        <a href="../devis/details.php?id=<?php echo $did; ?>" class="compta-devis-payee-card__btn compta-devis-payee-card__btn--secondary"><i class="fas fa-eye" aria-hidden="true"></i> Détail</a>
                                        <a href="../devis/facture.php?id=<?php echo $fid; ?>" class="compta-devis-payee-card__btn compta-devis-payee-card__btn--primary"><i class="fas fa-file-invoice" aria-hidden="true"></i> Facture</a>
                                    </td>
                                </tr>
                        <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="compta-panel-bons-retour" class="compta-panel compta-panel--bons-retour <?php echo $tab_bons_retour_active ? 'is-active' : ''; ?>" role="tabpanel" aria-labelledby="compta-tab-bons-retour" <?php echo $tab_bons_retour_active ? '' : 'hidden'; ?> data-compta-panel="bons_retour">
            <div class="compta-hero compta-hero--bl compta-bl-hero compta-bl-hero--premium">
                <div class="compta-hero__copy">
                    <p class="compta-bl-eyebrow">B2B · Retours marchandises</p>
                    <h2 class="compta-hero__title" id="compta-br-hero-title">Bons de retour</h2>
                    <div class="compta-hero__actions compta-bl-hero__actions">
                        <a href="#compta-br-clients-anchor" class="compta-btn compta-btn--primary"><i class="fas fa-people-group" aria-hidden="true"></i> Liste clients &amp; BR</a>
                    </div>
                </div>
            </div>

            <div class="compta-bl-body">

            <?php if (!$br_tables_ok_compta): ?>
                <div class="compta-bl-alerts" role="status">
                    <p class="message error compta-bl-msg compta-bl-msg--standalone"><i class="fas fa-database" aria-hidden="true"></i> Tables bons de retour absentes : exécutez <code>php migrations/run_create_bons_retour_tables.php</code>.</p>
                </div>
            <?php else: ?>
                <h3 class="compta-section-title compta-bl-synth-title"><i class="fas fa-undo" aria-hidden="true"></i> Bons de retour</h3>

                <div class="compta-bl-kpis" aria-label="Synthèse des retours B2B">
                    <div class="compta-bl-kpi">
                        <span class="compta-bl-kpi__ic" aria-hidden="true"><i class="fas fa-file-alt"></i></span>
                        <div class="compta-bl-kpi__body">
                            <span class="compta-bl-kpi__label">Bons de retour enregistrés</span>
                            <span class="compta-bl-kpi__value"><?php echo (int) $br_kpi_nb; ?></span>
                        </div>
                    </div>
                    <div class="compta-bl-kpi">
                        <span class="compta-bl-kpi__ic" aria-hidden="true"><i class="fas fa-file-invoice-dollar"></i></span>
                        <div class="compta-bl-kpi__body">
                            <span class="compta-bl-kpi__label">Total HT retours</span>
                            <span class="compta-bl-kpi__value"><?php echo number_format($br_kpi_total_ht, 0, ',', ' '); ?> <small>FCFA</small></span>
                        </div>
                    </div>
                    <div class="compta-bl-kpi">
                        <span class="compta-bl-kpi__ic" aria-hidden="true"><i class="fas fa-users"></i></span>
                        <div class="compta-bl-kpi__body">
                            <span class="compta-bl-kpi__label">Clients distincts</span>
                            <span class="compta-bl-kpi__value"><?php echo (int) $br_kpi_nb_clients; ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <h3 id="compta-br-clients-anchor" class="compta-section-title compta-section-title--spaced compta-bl-list-title"><i class="fas fa-people-group" aria-hidden="true"></i> Clients &amp; bons de retour</h3>

            <?php if ($br_tables_ok_compta && empty($br_clients_list_compta)): ?>
                <div class="compta-blank compta-bl-empty">
                    <p><i class="fas fa-people-group" aria-hidden="true"></i> Aucun client B2B avec bon de retour pour le moment.</p>
                </div>
            <?php elseif ($br_tables_ok_compta && !empty($br_clients_list_compta)): ?>
                <?php $br_nb_contacts_compta = count($br_clients_list_compta); ?>
                <div class="bl-tab-surface compta-bl-tab-surface">
                    <header class="bl-contacts-hero">
                        <div class="bl-contacts-hero__icon-wrap" aria-hidden="true">
                            <i class="fas fa-people-group"></i>
                        </div>
                        <div class="bl-contacts-hero__copy">
                            <h2 class="bl-contacts-hero__title">Contacts B2B</h2>
                            <p class="bl-contacts-hero__lead compta-bl-contacts-hero__lead">Clients ayant enregistré au moins un bon de retour.</p>
                        </div>
                        <div class="bl-contacts-hero__stat" title="Nombre de contacts listés">
                            <span class="bl-contacts-hero__stat-num"><?php echo (int) $br_nb_contacts_compta; ?></span>
                            <span class="bl-contacts-hero__stat-label">contact<?php echo $br_nb_contacts_compta > 1 ? 's' : ''; ?></span>
                        </div>
                    </header>
                    <div class="compta-panel-table-wrap compta-bl-clients-table-wrap">
                        <table class="data-table compta-bl-clients-table" aria-labelledby="compta-br-clients-anchor">
                            <thead>
                                <tr>
                                    <th scope="col" class="compta-bl-clients-table__th compta-bl-clients-table__th--client"><span class="compta-bl-clients-table__th-label"><i class="fas fa-building" aria-hidden="true"></i> Client</span></th>
                                    <th scope="col" class="compta-bl-clients-table__th"><span class="compta-bl-clients-table__th-label"><i class="fas fa-phone-alt" aria-hidden="true"></i> Téléphone</span></th>
                                    <th scope="col" class="compta-bl-clients-table__th"><span class="compta-bl-clients-table__th-label"><i class="fas fa-envelope" aria-hidden="true"></i> Email</span></th>
                                    <th scope="col" class="compta-table-col-num compta-bl-clients-table__th compta-bl-clients-table__th--bl"><span class="compta-bl-clients-table__th-label"><i class="fas fa-undo" aria-hidden="true"></i> BR</span></th>
                                    <th scope="col" class="compta-table-col-actions compta-bl-clients-table__th compta-bl-clients-table__th--actions"><span class="compta-bl-clients-table__th-label"><i class="fas fa-external-link-alt" aria-hidden="true"></i> Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                    <?php foreach ($br_clients_list_compta as $cl): ?>
                        <?php
                        $cid = (int) $cl['id'];
                        $nb_br_c = (int) ($cl['nb_br'] ?? 0);
                        $rs_c = trim($cl['raison_sociale'] ?? '');
                        $tel_raw = trim((string) ($cl['telephone'] ?? ''));
                        $email_raw = trim((string) ($cl['email'] ?? ''));
                        ?>
                                <tr class="compta-bl-clients-table__row">
                                    <td class="compta-bl-clients-table__cell compta-bl-clients-table__cell--client">
                                        <div class="compta-bl-clients-table__client">
                                            <span class="compta-bl-clients-table__company"><?php echo htmlspecialchars($rs_c ?: '—'); ?></span>
                                        </div>
                                    </td>
                                    <td class="compta-bl-clients-table__cell compta-bl-clients-table__cell--meta">
                                        <?php if ($tel_raw !== ''): ?>
                                            <a class="compta-bl-clients-table__link compta-bl-clients-table__link--tel" href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $tel_raw)); ?>"><?php echo htmlspecialchars($tel_raw); ?></a>
                                        <?php else: ?>
                                            <span class="compta-bl-clients-table__empty">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="compta-bl-clients-table__cell compta-bl-clients-table__cell--meta">
                                        <?php if ($email_raw !== ''): ?>
                                            <a class="compta-bl-clients-table__link compta-bl-clients-table__link--mail" href="mailto:<?php echo htmlspecialchars($email_raw); ?>"><?php echo htmlspecialchars($email_raw); ?></a>
                                        <?php else: ?>
                                            <span class="compta-bl-clients-table__empty">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="compta-table-col-num compta-bl-clients-table__cell compta-bl-clients-table__cell--bl">
                                        <span class="compta-bl-clients-table__bl-pill" title="Nombre de bons de retour"><?php echo (int) $nb_br_c; ?></span>
                                    </td>
                                    <td class="compta-table-actions compta-bl-clients-table__cell compta-bl-clients-table__cell--cta">
                                        <a class="compta-bl-clients-table__cta" href="../devis/br_par_client.php?id=<?php echo $cid; ?>" aria-label="Voir les bons de retour du client"><span class="compta-bl-clients-table__cta-text">Bons de retour</span><i class="fas fa-arrow-right compta-bl-clients-table__cta-ic" aria-hidden="true"></i></a>
                                    </td>
                                </tr>
                    <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            </div><!-- .compta-bl-body -->
        </div>
    </section>
    </div>

    <script>
    (function() {
        var tabs = document.querySelectorAll('[data-compta-tab]');
        var panels = document.querySelectorAll('[data-compta-panel]');
        function showTab(which) {
            tabs.forEach(function(btn) {
                var w = btn.getAttribute('data-compta-tab');
                var on = (w === which);
                btn.classList.toggle('is-active', on);
                btn.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            panels.forEach(function(p) {
                var w = p.getAttribute('data-compta-panel');
                var on = (w === which);
                if (on) {
                    p.removeAttribute('hidden');
                    p.classList.add('is-active');
                } else {
                    p.setAttribute('hidden', 'hidden');
                    p.classList.remove('is-active');
                }
            });
            if (window.history && window.history.replaceState) {
                try {
                    var u = new URL(window.location.href);
                    u.searchParams.set('tab', which);
                    window.history.replaceState({}, '', u);
                } catch (e) {}
            }
            try {
                var hubForm = document.getElementById('compta-hub-synthese-form');
                if (hubForm) {
                    var ti = hubForm.querySelector('input[name="tab"]');
                    if (ti) { ti.value = which; }
                }
            } catch (e2) {}
        }
        tabs.forEach(function(btn) {
            btn.addEventListener('click', function() {
                showTab(btn.getAttribute('data-compta-tab'));
            });
        });
    })();
    (function() {
        var sel = document.getElementById('compta-v-periode');
        var form = document.getElementById('compta-ventes-form');
        function toggleVentesChamps(p) {
            var map = { jour: 'compta-wrap-v-jour', plage: 'compta-wrap-v-plage', mois: 'compta-wrap-v-mois', annee: 'compta-wrap-v-annee' };
            Object.keys(map).forEach(function(k) {
                var el = document.getElementById(map[k]);
                if (!el) { return; }
                if (k === p) {
                    el.classList.remove('is-hidden');
                } else {
                    el.classList.add('is-hidden');
                }
            });
        }
        if (sel) {
            sel.addEventListener('change', function() { toggleVentesChamps(this.value); });
        }
        if (form) {
            form.addEventListener('submit', function() {
                var panels = form.querySelectorAll('.compta-ventes-filter__panel');
                panels.forEach(function(panel) {
                    var hide = panel.classList.contains('is-hidden');
                    panel.querySelectorAll('input, select').forEach(function(inp) {
                        inp.disabled = hide;
                    });
                });
            });
        }
    })();
    (function() {
        var sel = document.getElementById('compta-d-periode');
        var form = document.getElementById('compta-dep-filtres-form');
        function toggleDepPanels(p) {
            var map = { jour: 'compta-wrap-d-jour', semaine: 'compta-wrap-d-semaine', plage: 'compta-wrap-d-plage' };
            Object.keys(map).forEach(function(k) {
                var el = document.getElementById(map[k]);
                if (!el) { return; }
                if (k === p) {
                    el.classList.remove('is-hidden');
                } else {
                    el.classList.add('is-hidden');
                }
            });
        }
        if (sel) {
            sel.addEventListener('change', function() { toggleDepPanels(this.value); });
        }
        if (form) {
            form.addEventListener('submit', function() {
                var panels = form.querySelectorAll('.compta-ventes-filter__panel');
                panels.forEach(function(panel) {
                    var hide = panel.classList.contains('is-hidden');
                    panel.querySelectorAll('input, select').forEach(function(inp) {
                        inp.disabled = hide;
                    });
                });
            });
        }
    })();
    (function() {
        var sel = document.getElementById('compta-h-periode');
        var form = document.getElementById('compta-hub-synthese-form');
        function toggleHubPanels(p) {
            var map = { jour: 'compta-wrap-h-jour', semaine: 'compta-wrap-h-semaine', plage: 'compta-wrap-h-plage' };
            Object.keys(map).forEach(function(k) {
                var el = document.getElementById(map[k]);
                if (!el) { return; }
                if (k === p) {
                    el.classList.remove('is-hidden');
                } else {
                    el.classList.add('is-hidden');
                }
            });
        }
        if (sel) {
            sel.addEventListener('change', function() { toggleHubPanels(this.value); });
        }
        if (form) {
            form.addEventListener('submit', function() {
                form.querySelectorAll('.compta-ventes-filter__panel').forEach(function(panel) {
                    var hide = panel.classList.contains('is-hidden');
                    panel.querySelectorAll('input, select').forEach(function(inp) {
                        inp.disabled = hide;
                    });
                });
            });
        }
    })();
    (function() {
        var sel = document.getElementById('compta-c-periode');
        var form = document.getElementById('compta-caisse-filters-form');
        var anchor = document.getElementById('compta-wrap-c-anchor');
        var plage = document.getElementById('compta-wrap-c-plage');
        var anchorLbl = document.getElementById('compta-c-anchor-label');
        function syncAnchorLabel(p) {
            if (!anchorLbl) {
                return;
            }
            if (p === 'semaine') {
                anchorLbl.textContent = 'Semaine contenant le';
            } else if (p === 'jour') {
                anchorLbl.textContent = 'Date';
            }
        }
        function toggleCaisse(p) {
            var isPlage = (p === 'plage');
            if (anchor) {
                anchor.classList.toggle('is-hidden', isPlage);
            }
            if (plage) {
                plage.classList.toggle('is-hidden', !isPlage);
            }
            syncAnchorLabel(p);
        }
        if (sel) {
            sel.addEventListener('change', function() {
                toggleCaisse(this.value);
            });
        }
        if (form) {
            form.addEventListener('submit', function() {
                form.querySelectorAll('.compta-ventes-filter__panel').forEach(function(panel) {
                    var hide = panel.classList.contains('is-hidden');
                    panel.querySelectorAll('select').forEach(function(inp) {
                        inp.disabled = hide;
                    });
                });
            });
        }
    })();
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
