<?php
/**
 * Bilan comptable synthétique — agrégation par plage de dates
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/model_commandes_admin.php';
require_once __DIR__ . '/model_depenses.php';
require_once __DIR__ . '/model_caisse_compta.php';
require_once __DIR__ . '/model_bl.php';
require_once __DIR__ . '/model_factures_mensuelles.php';

/**
 * Analyse les paramètres GET du bilan (b_periode: jour|mois|plage).
 *
 * @param array<string, mixed> $get
 * @return array{
 *   type:string,
 *   date_debut:string,
 *   date_fin:string,
 *   libelle:string,
 *   annee:int,
 *   mois:int,
 *   jour:int,
 *   b_date_jour:string,
 *   b_date_debut:string,
 *   b_date_fin:string
 * }
 */
function bilan_comptable_parse_periode(array $get) {
    $b_periode = isset($get['b_periode']) ? trim((string) $get['b_periode']) : 'mois';
    if (!in_array($b_periode, ['jour', 'mois', 'plage'], true)) {
        $b_periode = 'mois';
    }

    $v_annee = (int) date('Y');
    $v_mois = (int) date('n');
    $v_jour = (int) date('j');

    $b_date_debut = isset($get['b_date_debut']) ? trim((string) $get['b_date_debut']) : '';
    $b_date_fin = isset($get['b_date_fin']) ? trim((string) $get['b_date_fin']) : '';
    $b_date_jour = isset($get['b_date_jour']) ? trim((string) $get['b_date_jour']) : '';

    if ($b_periode === 'mois') {
        $v_annee = isset($get['b_annee_mois']) ? (int) $get['b_annee_mois'] : (int) date('Y');
        $v_mois = isset($get['b_mois']) ? (int) $get['b_mois'] : (int) date('n');
    } elseif ($b_periode === 'jour') {
        if ($b_date_jour !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $b_date_jour)
            && checkdate((int) substr($b_date_jour, 5, 2), (int) substr($b_date_jour, 8, 2), (int) substr($b_date_jour, 0, 4))) {
            $v_annee = (int) substr($b_date_jour, 0, 4);
            $v_mois = (int) substr($b_date_jour, 5, 2);
            $v_jour = (int) substr($b_date_jour, 8, 2);
        }
    } elseif ($b_periode === 'plage') {
        $ok_debut = $b_date_debut !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $b_date_debut)
            && checkdate((int) substr($b_date_debut, 5, 2), (int) substr($b_date_debut, 8, 2), (int) substr($b_date_debut, 0, 4));
        $ok_fin = $b_date_fin !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $b_date_fin)
            && checkdate((int) substr($b_date_fin, 5, 2), (int) substr($b_date_fin, 8, 2), (int) substr($b_date_fin, 0, 4));
        if (!$ok_debut) {
            $b_date_debut = date('Y-m-01');
        }
        if (!$ok_fin) {
            $b_date_fin = date('Y-m-d');
        }
        if (strcmp($b_date_debut, $b_date_fin) > 0) {
            $t = $b_date_debut;
            $b_date_debut = $b_date_fin;
            $b_date_fin = $t;
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

    if ($b_periode === 'jour') {
        $d1 = $d2 = sprintf('%04d-%02d-%02d', $v_annee, $v_mois, $v_jour);
        if ($b_date_jour === '') {
            $b_date_jour = $d1;
        }
        $libelle = date('d/m/Y', strtotime($d1));
    } elseif ($b_periode === 'mois') {
        $d1 = sprintf('%04d-%02d-01', $v_annee, $v_mois);
        $d2 = date('Y-m-t', mktime(0, 0, 0, $v_mois, 1, $v_annee));
        $mois_fr = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $libelle = ucfirst($mois_fr[$v_mois] ?? '') . ' ' . $v_annee;
        if ($b_date_jour === '') {
            $b_date_jour = date('Y-m-d');
        }
    } else {
        $d1 = $b_date_debut;
        $d2 = $b_date_fin;
        $libelle = date('d/m/Y', strtotime($d1)) . ' – ' . date('d/m/Y', strtotime($d2));
        if ($b_date_jour === '') {
            $b_date_jour = date('Y-m-d');
        }
    }

    return [
        'type' => $b_periode,
        'date_debut' => $d1,
        'date_fin' => $d2,
        'libelle' => $libelle,
        'annee' => $v_annee,
        'mois' => $v_mois,
        'jour' => $v_jour,
        'b_date_jour' => $b_date_jour,
        'b_date_debut' => $b_periode === 'plage' ? $b_date_debut : '',
        'b_date_fin' => $b_periode === 'plage' ? $b_date_fin : '',
    ];
}

/**
 * @return array<string, mixed>
 */
function bilan_comptable_collecter_donnees($date_debut, $date_fin, $limit_details = 500) {
    $d1 = (string) $date_debut;
    $d2 = (string) $date_fin;

    $export_large = (int) $limit_details <= 0;
    $lim_dep = $export_large ? 'all' : max(100, min(5000, (int) $limit_details));
    $lim_caisse = $export_large ? 50000 : max(100, min(5000, (int) $limit_details));
    $lim_bl = $export_large ? 8000 : max(50, min(2000, (int) $limit_details));
    $lim_fm = $export_large ? 3000 : max(50, min(1000, (int) $limit_details));

    $commandes = get_commandes_by_periode('plage', null, null, $d1, $d2, null, true);
    $stats_web = get_stats_ventes_commandes_vendues($commandes);

    $depenses = [];
    $totaux_dep = ['nb' => 0, 'sum_ht' => 0.0, 'sum_tva' => 0.0, 'sum_ttc' => 0.0];
    if (depenses_tables_ok()) {
        depenses_seed_categories_if_needed();
        $depenses = get_depenses_filtrees([
            'date_debut' => $d1,
            'date_fin' => $d2,
            'limit' => $lim_dep,
        ]);
        $totaux_dep = depenses_calculer_totaux($depenses);
    }

    $caisse_liste = [];
    $caisse_totaux = ['total_ttc' => 0.0, 'nb' => 0, 'par_mode' => []];
    if (function_exists('caisse_tables_exist') && caisse_tables_exist()) {
        $caisse_liste = caisse_compta_get_ventes_filtrees([
            'date_debut' => $d1,
            'date_fin' => $d2,
            'mode_paiement' => '',
            'admin_id' => 0,
            'q' => '',
            'limit' => $lim_caisse,
        ]);
        $caisse_totaux = caisse_compta_calculer_totaux($caisse_liste);
    }

    $stats_bl = function_exists('bl_tables_available') && bl_tables_available()
        ? get_stats_bl_compta_periode($d1, $d2)
        : ['nb_bl' => 0, 'nb_clients' => 0, 'somme_bl_ht' => 0.0];

    $stats_fm = factures_mensuelles_table_ok()
        ? get_somme_et_nb_factures_mensuelles_periode($d1, $d2)
        : ['somme_ht' => 0.0, 'nb_factures' => 0];

    $bl_detail = [];
    if (function_exists('bl_tables_available') && bl_tables_available()) {
        $bl_detail = get_bl_compta_entre_dates($d1, $d2, $lim_bl);
    }

    $fm_detail = [];
    if (factures_mensuelles_table_ok()) {
        $fm_detail = get_factures_mensuelles_chevauchant_periode($d1, $d2, $lim_fm);
    }

    return [
        'commandes' => $commandes,
        'stats_web' => $stats_web,
        'depenses' => $depenses,
        'totaux_dep' => $totaux_dep,
        'caisse_liste' => $caisse_liste,
        'caisse_totaux' => $caisse_totaux,
        'stats_bl' => $stats_bl,
        'stats_fm' => $stats_fm,
        'bl_detail' => $bl_detail,
        'fm_detail' => $fm_detail,
    ];
}
