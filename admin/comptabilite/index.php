<?php
/**
 * Espace Comptabilité — hub à onglets (ventes, dépenses, BL / factures HT)
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

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$depenses_ok = depenses_tables_ok();
$depense_flash_ok = isset($_GET['dep_ok']) && $_GET['dep_ok'] === '1';
$depense_error_msg = '';
$open_depense_modal = false;

if ($depenses_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['depense_ajout'])) {
    $tok = $_POST['csrf_token'] ?? '';
    if (!hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), (string) $tok)) {
        $depense_error_msg = 'Session expirée. Rechargez la page.';
        $open_depense_modal = true;
    } else {
        $r = process_depense_ajout($_POST, (int) ($_SESSION['admin_id'] ?? 0));
        if (!empty($r['success'])) {
            header('Location: index.php?tab=depenses&dep_ok=1');
            exit;
        }
        $depense_error_msg = $r['message'] ?? 'Erreur lors de l\'enregistrement.';
        if ($depense_error_msg !== '') {
            $open_depense_modal = true;
        }
    }
}

$d_date_debut = isset($_GET['d_date_debut']) ? trim((string) $_GET['d_date_debut']) : '';
$d_date_fin = isset($_GET['d_date_fin']) ? trim((string) $_GET['d_date_fin']) : '';
$d_categorie = isset($_GET['d_categorie']) ? (int) $_GET['d_categorie'] : 0;
$d_type_dep = isset($_GET['d_type']) && in_array($_GET['d_type'], ['sans_tva', 'avec_tva', ''], true) ? $_GET['d_type'] : '';
$d_q = isset($_GET['d_q']) ? trim((string) $_GET['d_q']) : '';

$d_date_debut_ok = $d_date_debut !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d_date_debut)
    && checkdate((int) substr($d_date_debut, 5, 2), (int) substr($d_date_debut, 8, 2), (int) substr($d_date_debut, 0, 4));
$d_date_fin_ok = $d_date_fin !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d_date_fin)
    && checkdate((int) substr($d_date_fin, 5, 2), (int) substr($d_date_fin, 8, 2), (int) substr($d_date_fin, 0, 4));
if (!$d_date_debut_ok) {
    $d_date_debut = date('Y-m-01');
}
if (!$d_date_fin_ok) {
    $d_date_fin = date('Y-m-d');
}
if (strcmp($d_date_debut, $d_date_fin) > 0) {
    $t = $d_date_debut;
    $d_date_debut = $d_date_fin;
    $d_date_fin = $t;
}

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

$c_date_debut = isset($_GET['c_date_debut']) ? trim((string) $_GET['c_date_debut']) : '';
$c_date_fin = isset($_GET['c_date_fin']) ? trim((string) $_GET['c_date_fin']) : '';
$c_modes_list = ['especes', 'carte', 'mobile_money', 'cheque', 'mixte', 'autre'];
$c_mode_raw = isset($_GET['c_mode']) ? trim((string) $_GET['c_mode']) : '';
$c_mode = ($c_mode_raw === '' || in_array($c_mode_raw, $c_modes_list, true)) ? $c_mode_raw : '';
$c_admin = isset($_GET['c_admin']) ? (int) $_GET['c_admin'] : 0;
$c_q = isset($_GET['c_q']) ? trim((string) $_GET['c_q']) : '';

$c_date_debut_ok = $c_date_debut !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $c_date_debut)
    && checkdate((int) substr($c_date_debut, 5, 2), (int) substr($c_date_debut, 8, 2), (int) substr($c_date_debut, 0, 4));
$c_date_fin_ok = $c_date_fin !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $c_date_fin)
    && checkdate((int) substr($c_date_fin, 5, 2), (int) substr($c_date_fin, 8, 2), (int) substr($c_date_fin, 0, 4));
if (!$c_date_debut_ok) {
    $c_date_debut = date('Y-m-01');
}
if (!$c_date_fin_ok) {
    $c_date_fin = date('Y-m-d');
}
if (strcmp($c_date_debut, $c_date_fin) > 0) {
    $tc = $c_date_debut;
    $c_date_debut = $c_date_fin;
    $c_date_fin = $tc;
}

$caisse_ok = function_exists('caisse_tables_exist') && caisse_tables_exist();
$caisse_admins_filtre = [];
$caisse_ventes_liste = [];
$caisse_totaux = ['total_ttc' => 0.0, 'nb' => 0, 'par_mode' => []];
if ($caisse_ok) {
    $caisse_admins_filtre = caisse_compta_liste_admins_actifs();
    $caisse_ventes_liste = caisse_compta_get_ventes_filtrees([
        'date_debut' => $c_date_debut,
        'date_fin' => $c_date_fin,
        'mode_paiement' => $c_mode,
        'admin_id' => $c_admin,
        'q' => $c_q,
    ]);
    $caisse_totaux = caisse_compta_calculer_totaux($caisse_ventes_liste);
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
$factures_mois = $fm_ok ? get_factures_mensuelles_par_mois($bl_sel_annee, $bl_sel_mois) : [];
$bl_clients_list_compta = $bl_tables_ok ? get_clients_b2b_avec_bl() : [];

$tab_valid = ['ventes', 'depenses', 'bl', 'caisse'];
$active_tab = isset($_GET['tab']) && in_array($_GET['tab'], $tab_valid, true) ? $_GET['tab'] : 'ventes';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['depense_ajout'])) {
    $active_tab = 'depenses';
}

$is_admin_role = in_array(($_SESSION['admin_role'] ?? ''), ['admin', 'informaticien'], true);

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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comptabilité — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/compta-depenses.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/compta-bl.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/compta-caisse.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/compta-bilan.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-devis-compta-pages.css<?php echo asset_version_query(); ?>">
</head>
<body<?php echo !empty($open_depense_modal) ? ' class="compta-dep-modal-open"' : ''; ?>>
    <?php include '../includes/nav.php'; ?>

    <div class="page-compta-admin">
    <div class="content-header dashboard-hero page-compta-hero">
        <div class="dashboard-hero-text">
            <p class="dashboard-eyebrow">Finance &amp; suivi</p>
            <h1><i class="fas fa-calculator" aria-hidden="true"></i> Comptabilité</h1>
            <p class="dashboard-subtitle">Centralisez les ventes boutique, les dépenses et le suivi des bons de livraison HT (B2B).</p>
        </div>
        <div class="compta-hub-bilan-cta">
            <a href="bilan.php" class="compta-btn compta-btn--bilan"><i class="fas fa-scale-balanced" aria-hidden="true"></i> Bilan comptable &amp; export CSV</a>
        </div>
    </div>

    <section class="content-section compta-page page-compta-section" aria-label="Espace comptabilité">
        <div class="compta-intro">
            <p><strong>Rappel :</strong> les factures mensuelles B2B sont en <strong>HT</strong> (sans TVA). Les commandes e-commerce suivent les statuts et montants enregistrés sur chaque commande. Les <strong>ventes caisse</strong> (magasin) sont en <strong>TTC</strong>, issues des tickets enregistrés à la caisse.</p>
        </div>

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
            </div>
        </div>

        <div id="compta-panel-ventes" class="compta-panel <?php echo $tab_ventes_active ? 'is-active' : ''; ?>" role="tabpanel" aria-labelledby="compta-tab-ventes" <?php echo $tab_ventes_active ? '' : 'hidden'; ?> data-compta-panel="ventes">
            <div class="compta-hero compta-hero--ventes">
                <div class="compta-hero__copy">
                    <h2 class="compta-hero__title">Ventes &amp; commandes e-commerce</h2>
                    <p class="compta-hero__lead">Accédez à la file des commandes à traiter, aux détails et à l’historique des ventes pour analyser les produits vendus et les montants.</p>
                    <div class="compta-hero__actions">
                        <a href="../commandes/index.php" class="compta-btn compta-btn--primary"><i class="fas fa-list" aria-hidden="true"></i> Ouvrir les commandes</a>
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
                        <select name="v_periode" id="compta-v-periode" class="compta-ventes-filter__select" aria-describedby="compta-ventes-filter-help">
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

                <p id="compta-ventes-filter-help" class="compta-ventes-filter__help">
                    <?php if ($ventes_filtre_actif): ?>
                        Filtre actif : commandes <strong>livrées</strong> ou <strong>payées</strong> sur la période <strong><?php echo htmlspecialchars($libelle_periode_ventes); ?></strong>.
                        <a href="index.php?tab=ventes">Revenir à la vue globale</a>.
                    <?php else: ?>
                        <strong>Vue globale</strong> : totaux et liste de toutes les commandes livrées ou payées (toutes dates). Choisissez un type de filtre, les dates si besoin, puis <strong>Afficher</strong> pour restreindre à une période.
                    <?php endif; ?>
                </p>
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

            <div class="compta-info-card">
                <i class="fas fa-lightbulb" aria-hidden="true"></i>
                <div>
                    <strong>Rappel</strong>
                    <p>Les filtres s’appliquent à la <strong>date de commande</strong>. Pour une vue tableau détaillée ou des statistiques sur toutes les commandes (tous statuts), utilisez <?php if ($is_admin_role): ?><a href="../commandes/historique-ventes.php">l’historique des ventes</a><?php else: ?>l’historique des ventes (accès administrateur)<?php endif; ?>.</p>
                </div>
            </div>
        </div>

        <div id="compta-panel-depenses" class="compta-panel compta-panel--depenses <?php echo $tab_depenses_active ? 'is-active' : ''; ?>" role="tabpanel" aria-labelledby="compta-tab-depenses" <?php echo $tab_depenses_active ? '' : 'hidden'; ?> data-compta-panel="depenses">
            <div class="compta-hero compta-hero--depenses compta-dep-hero compta-dep-hero--premium">
                <div class="compta-hero__copy">
                    <p class="compta-dep-eyebrow">Charges &amp; suivi</p>
                    <h2 class="compta-hero__title" id="compta-dep-hero-title">Dépenses</h2>
                    <p class="compta-hero__lead">Enregistrez les charges en <strong>HT</strong>, avec ou sans <strong>TVA</strong>, par catégorie. Les montants sont affichés en FCFA ; filtres par période et libellé.</p>
                </div>
                <?php if ($depenses_ok): ?>
                <div class="compta-hero__actions compta-dep-hero__actions">
                    <button type="button" class="compta-btn compta-btn--primary compta-dep-btn-open-modal" id="compta-dep-open-modal" aria-haspopup="dialog" aria-controls="compta-dep-modal">
                        <i class="fas fa-plus-circle" aria-hidden="true"></i> Enregistrer une dépense
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!$depenses_ok): ?>
                <p class="message error compta-dep-msg compta-dep-msg--standalone"><i class="fas fa-database" aria-hidden="true"></i> Tables absentes : exécutez la migration <code>migrations/create_depenses_compta.sql</code> (ou <code>migration_admin_b2b_structure.sql</code>).</p>
            <?php else: ?>
            <div class="compta-dep-body">
                <?php
                $compta_dep_show_alerts = $depense_flash_ok || $depense_error_msg !== '';
                ?>
                <?php if ($compta_dep_show_alerts): ?>
                <div class="compta-dep-alerts" role="region" aria-label="Messages dépenses">
                <?php endif; ?>
                <?php if ($depense_flash_ok): ?>
                    <div class="message success compta-dep-msg"><i class="fas fa-check-circle" aria-hidden="true"></i> Dépense enregistrée.</div>
                <?php endif; ?>
                <?php if ($depense_error_msg !== ''): ?>
                    <div class="message error compta-dep-msg"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($depense_error_msg); ?></div>
                <?php endif; ?>
                <?php if ($compta_dep_show_alerts): ?>
                </div>
                <?php endif; ?>

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

                <div class="compta-dep-grid">
                    <section class="compta-dep-card compta-dep-card--filters" aria-labelledby="compta-dep-filtres-title">
                        <h3 id="compta-dep-filtres-title" class="compta-dep-card__title"><i class="fas fa-filter" aria-hidden="true"></i> Filtres</h3>
                        <form method="get" action="index.php" class="compta-dep-filters">
                            <input type="hidden" name="tab" value="depenses">
                            <div class="compta-dep-filters__grid">
                                <div class="compta-dep-filters__field">
                                    <label for="d_date_debut">Du</label>
                                    <input type="date" name="d_date_debut" id="d_date_debut" value="<?php echo htmlspecialchars($d_date_debut); ?>">
                                </div>
                                <div class="compta-dep-filters__field">
                                    <label for="d_date_fin">Au</label>
                                    <input type="date" name="d_date_fin" id="d_date_fin" value="<?php echo htmlspecialchars($d_date_fin); ?>">
                                </div>
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
                            <div class="compta-dep-filters__actions">
                                <button type="submit" class="compta-dep-filters__btn"><i class="fas fa-search" aria-hidden="true"></i> Appliquer</button>
                                <a href="index.php?tab=depenses" class="compta-dep-filters__reset">Réinitialiser</a>
                            </div>
                        </form>
                    </section>
                </div>

                <h3 class="compta-section-title compta-dep-list-title"><i class="fas fa-list" aria-hidden="true"></i> Détail des dépenses</h3>

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

                <div id="compta-dep-modal" class="compta-dep-modal<?php echo !empty($open_depense_modal) ? ' is-open' : ''; ?>" role="dialog" aria-modal="true" aria-labelledby="compta-dep-modal-title" <?php echo !empty($open_depense_modal) ? '' : 'aria-hidden="true"'; ?>>
                    <div class="compta-dep-modal__backdrop" data-compta-dep-modal-close tabindex="-1" aria-hidden="true"></div>
                    <div class="compta-dep-modal__scroll">
                        <div class="compta-dep-modal__inner">
                            <header class="compta-dep-modal__head">
                                <div class="compta-dep-modal__head-text">
                                    <h2 id="compta-dep-modal-title"><i class="fas fa-receipt" aria-hidden="true"></i> Nouvelle dépense</h2>
                                    <p>Saisissez le montant <strong>HT</strong> ; la TVA et le TTC sont calculés selon le type choisi.</p>
                                </div>
                                <button type="button" class="compta-dep-modal__close" data-compta-dep-modal-close aria-label="Fermer la fenêtre">
                                    <i class="fas fa-times" aria-hidden="true"></i>
                                </button>
                            </header>
                            <div class="compta-dep-modal__body">
                                <form method="post" action="index.php?tab=depenses" class="compta-dep-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                                    <input type="hidden" name="depense_ajout" value="1">
                                    <div class="compta-dep-form__row">
                                        <label for="dep_libelle">Libellé <span class="req">*</span></label>
                                        <input type="text" name="libelle" id="dep_libelle" required maxlength="255" placeholder="Ex. Facture électricité" autocomplete="off">
                                    </div>
                                    <div class="compta-dep-form__row compta-dep-form__row--2">
                                        <div>
                                            <label for="dep_date">Date <span class="req">*</span></label>
                                            <input type="date" name="date_depense" id="dep_date" required value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
                                        </div>
                                        <div>
                                            <label for="dep_cat">Catégorie</label>
                                            <select name="categorie_id" id="dep_cat">
                                                <option value="0">— Non classé —</option>
                                                <?php foreach ($categories_dep as $cat): ?>
                                                    <option value="<?php echo (int) $cat['id']; ?>"><?php echo htmlspecialchars($cat['nom']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="compta-dep-form__row">
                                        <label for="dep_type">Type <span class="req">*</span></label>
                                        <select name="type_depense" id="dep_type" data-compta-dep-type>
                                            <option value="sans_tva">Hors TVA (HT = TTC)</option>
                                            <option value="avec_tva" selected>Avec TVA (HT + TVA = TTC)</option>
                                        </select>
                                    </div>
                                    <div class="compta-dep-form__row compta-dep-form__row--2">
                                        <div>
                                            <label for="dep_ht">Montant HT (FCFA) <span class="req">*</span></label>
                                            <input type="text" name="montant_ht" id="dep_ht" required inputmode="decimal" placeholder="0" pattern="[0-9]+([.,][0-9]+)?">
                                        </div>
                                        <div class="compta-dep-wrap-taux" id="compta-dep-wrap-taux">
                                            <label for="dep_tva">TVA (%)</label>
                                            <input type="text" name="taux_tva" id="dep_tva" inputmode="decimal" placeholder="20" value="20">
                                        </div>
                                    </div>
                                    <div class="compta-dep-form__row">
                                        <label for="dep_notes">Notes</label>
                                        <textarea name="notes" id="dep_notes" rows="3" placeholder="Réf. facture, fournisseur…"></textarea>
                                    </div>
                                    <div class="compta-dep-modal__form-actions">
                                        <button type="button" class="compta-dep-modal__btn-cancel" data-compta-dep-modal-close>Annuler</button>
                                        <button type="submit" class="compta-dep-submit"><i class="fas fa-save" aria-hidden="true"></i> Enregistrer la dépense</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                (function () {
                    var modal = document.getElementById('compta-dep-modal');
                    var openBtn = document.getElementById('compta-dep-open-modal');
                    if (!modal) return;

                    function getBody() {
                        return document.body;
                    }

                    function isOpen() {
                        return modal.classList.contains('is-open');
                    }

                    function setOpen(open) {
                        if (open) {
                            modal.classList.add('is-open');
                            modal.removeAttribute('aria-hidden');
                            getBody().classList.add('compta-dep-modal-open');
                        } else {
                            modal.classList.remove('is-open');
                            modal.setAttribute('aria-hidden', 'true');
                            getBody().classList.remove('compta-dep-modal-open');
                            if (openBtn) openBtn.focus();
                        }
                    }

                    modal.querySelectorAll('[data-compta-dep-modal-close]').forEach(function (el) {
                        el.addEventListener('click', function () {
                            setOpen(false);
                        });
                    });

                    if (openBtn) {
                        openBtn.addEventListener('click', function () {
                            setOpen(true);
                            var first = modal.querySelector('input:not([type="hidden"]), select, textarea, button[type="submit"]');
                            if (first) setTimeout(function () { first.focus(); }, 50);
                        });
                    }

                    document.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape' && isOpen()) {
                            e.preventDefault();
                            setOpen(false);
                        }
                    });

                    var sel = modal.querySelector('[data-compta-dep-type]');
                    var wrap = modal.querySelector('#compta-dep-wrap-taux');
                    if (sel && wrap) {
                        function syncTva() {
                            var on = sel.value === 'avec_tva';
                            wrap.style.display = on ? '' : 'none';
                            wrap.querySelectorAll('input').forEach(function (inp) { inp.disabled = !on; });
                        }
                        sel.addEventListener('change', syncTva);
                        syncTva();
                    }
                })();
                </script>
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
                    <h2 class="compta-hero__title" id="compta-bl-hero-title">Bons de livraison &amp; factures HT</h2>
                    <p class="compta-hero__lead">Filtrez par mois selon la <strong>date du BL</strong>. Les totaux ci-dessous incluent uniquement les BL au statut <strong>validé</strong> ou <strong>payé (comptabilité)</strong> — les brouillons sont exclus. Les factures mensuelles HT regroupent ces BL par client B2B.</p>
                    <div class="compta-hero__actions compta-bl-hero__actions">
                        <a href="#compta-bl-clients-anchor" class="compta-btn compta-btn--secondary"><i class="fas fa-people-group" aria-hidden="true"></i> Liste clients &amp; BL</a>
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
                    <p class="compta-bl-filter__help">Par défaut : <strong>mois en cours</strong>. La liste des mois inclut les périodes où au moins un BL existe.</p>
                </form>

                <div class="compta-bl-kpis" aria-label="Synthèse de la période">
                    <div class="compta-bl-kpi">
                        <span class="compta-bl-kpi__ic" aria-hidden="true"><i class="fas fa-file-invoice-dollar"></i></span>
                        <div class="compta-bl-kpi__body">
                            <span class="compta-bl-kpi__label">Factures HT enregistrées</span>
                            <span class="compta-bl-kpi__value"><?php echo number_format($stats_fm_mois['somme_ht'], 0, ',', ' '); ?> <small>FCFA</small></span>
                            <span class="compta-bl-kpi__sub"><?php echo (int) $stats_fm_mois['nb_factures']; ?> facture<?php echo $stats_fm_mois['nb_factures'] > 1 ? 's' : ''; ?> sur la période</span>
                        </div>
                    </div>
                    <div class="compta-bl-kpi">
                        <span class="compta-bl-kpi__ic" aria-hidden="true"><i class="fas fa-file-alt"></i></span>
                        <div class="compta-bl-kpi__body">
                            <span class="compta-bl-kpi__label">Bons de livraison (compta)</span>
                            <span class="compta-bl-kpi__value"><?php echo (int) $stats_bl_mois['nb_bl']; ?></span>
                            <span class="compta-bl-kpi__sub">Total HT : <?php echo number_format($stats_bl_mois['somme_bl_ht'], 0, ',', ' '); ?> FCFA · <?php echo (int) ($stats_bl_mois['nb_valide'] ?? 0); ?> bon(s) validé(s) (comptabilité)</span>
                        </div>
                    </div>
                    <div class="compta-bl-kpi">
                        <span class="compta-bl-kpi__ic" aria-hidden="true"><i class="fas fa-users"></i></span>
                        <div class="compta-bl-kpi__body">
                            <span class="compta-bl-kpi__label">Clients distincts</span>
                            <span class="compta-bl-kpi__value"><?php echo (int) $stats_bl_mois['nb_clients']; ?></span>
                            <span class="compta-bl-kpi__sub"><?php echo htmlspecialchars($periode_label_long); ?></span>
                        </div>
                    </div>
                </div>

                <p class="compta-bl-synth-note"><strong>Comptabilité :</strong> seuls les BL <strong>validés</strong> ou <strong>payés</strong> entrent dans les montants ci-dessus. Ouvrez une <strong>fiche client</strong> pour lister les BL, générer la <strong>facture mensuelle HT</strong> et consulter les documents.</p>
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
                            <p class="bl-contacts-hero__lead">Même présentation que l’onglet BL des devis : ouvrez une fiche pour voir tous les BL et <strong>générer la facture mensuelle HT</strong>.</p>
                        </div>
                        <div class="bl-contacts-hero__stat" title="Nombre de contacts listés">
                            <span class="bl-contacts-hero__stat-num"><?php echo (int) $bl_nb_contacts_compta; ?></span>
                            <span class="bl-contacts-hero__stat-label">contact<?php echo $bl_nb_contacts_compta > 1 ? 's' : ''; ?></span>
                        </div>
                    </header>
                    <div class="bl-contacts-grid" role="list">
                    <?php foreach ($bl_clients_list_compta as $cl): ?>
                        <?php
                        $cid = (int) $cl['id'];
                        $nb_bl_c = (int) ($cl['nb_bl'] ?? 0);
                        $contact_nom_c = trim(($cl['nom_contact'] ?? '') . ' ' . ($cl['prenom_contact'] ?? ''));
                        $rs_c = trim($cl['raison_sociale'] ?? '');
                        $initials_c = '?';
                        if ($rs_c !== '') {
                            $words_c = preg_split('/\s+/u', $rs_c, -1, PREG_SPLIT_NO_EMPTY);
                            if (count($words_c) >= 2) {
                                $initials_c = mb_strtoupper(
                                    mb_substr($words_c[0], 0, 1) . mb_substr($words_c[1], 0, 1),
                                    'UTF-8'
                                );
                            } else {
                                $initials_c = mb_strtoupper(mb_substr($rs_c, 0, min(2, mb_strlen($rs_c, 'UTF-8')), 'UTF-8'), 'UTF-8');
                            }
                        }
                        $adr_short_c = '';
                        if (!empty($cl['adresse'])) {
                            $adr_short_c = mb_substr($cl['adresse'], 0, 110);
                            if (mb_strlen($cl['adresse'], 'UTF-8') > 110) {
                                $adr_short_c .= '…';
                            }
                        }
                        $last_bl_c = !empty($cl['dernier_bl_date'])
                            ? date('d/m/Y · H:i', strtotime($cl['dernier_bl_date']))
                            : '—';
                        ?>
                        <article class="bl-contact-card" role="listitem">
                            <div class="bl-contact-card__inner">
                                <div class="bl-contact-card__head">
                                    <div class="bl-contact-card__avatar" aria-hidden="true"><?php echo htmlspecialchars($initials_c); ?></div>
                                    <div class="bl-contact-card__head-text">
                                        <h3 class="bl-contact-card__company"><?php echo htmlspecialchars($rs_c ?: '—'); ?></h3>
                                        <?php if ($contact_nom_c !== ''): ?>
                                            <p class="bl-contact-card__person">
                                                <i class="fas fa-user-tie" aria-hidden="true"></i>
                                                <?php echo htmlspecialchars($contact_nom_c); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="bl-contact-card__pill">
                                        <i class="fas fa-file-invoice" aria-hidden="true"></i>
                                        <?php echo $nb_bl_c; ?> BL
                                    </span>
                                </div>
                                <ul class="bl-contact-card__meta">
                                    <li class="bl-contact-card__meta-row">
                                        <span class="bl-contact-card__meta-ic" aria-hidden="true"><i class="fas fa-phone"></i></span>
                                        <span class="bl-contact-card__meta-val"><?php echo htmlspecialchars($cl['telephone'] ?? '—'); ?></span>
                                    </li>
                                    <li class="bl-contact-card__meta-row">
                                        <span class="bl-contact-card__meta-ic" aria-hidden="true"><i class="fas fa-envelope"></i></span>
                                        <span class="bl-contact-card__meta-val"><?php echo !empty($cl['email']) ? htmlspecialchars($cl['email']) : '—'; ?></span>
                                    </li>
                                    <?php if ($adr_short_c !== ''): ?>
                                    <li class="bl-contact-card__meta-row bl-contact-card__meta-row--address">
                                        <span class="bl-contact-card__meta-ic" aria-hidden="true"><i class="fas fa-location-dot"></i></span>
                                        <span class="bl-contact-card__meta-val"><?php echo htmlspecialchars($adr_short_c); ?></span>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                                <div class="bl-contact-card__foot">
                                    <div class="bl-contact-card__last">
                                        <span class="bl-contact-card__last-label">Dernier BL</span>
                                        <?php if (!empty($cl['dernier_bl_date'])): ?>
                                            <time class="bl-contact-card__last-date" datetime="<?php echo htmlspecialchars(date('c', strtotime($cl['dernier_bl_date']))); ?>"><?php echo htmlspecialchars($last_bl_c); ?></time>
                                        <?php else: ?>
                                            <span class="bl-contact-card__last-date">—</span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="bl-fiche-client.php?id=<?php echo $cid; ?>" class="bl-contact-card__cta">
                                        <span>Voir les bons &amp; facture</span>
                                        <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <h3 class="compta-section-title compta-section-title--spaced compta-bl-fm-title"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Factures mensuelles HT <span class="compta-section-title__per">(<?php echo htmlspecialchars($periode_label_long); ?>)</span></h3>

            <?php if (!$fm_ok): ?>
                <div class="compta-bl-alerts" role="status">
                    <p class="message error compta-bl-msg compta-bl-msg--standalone"><i class="fas fa-database" aria-hidden="true"></i> Tables absentes : exécutez <code>migrations/migration_admin_b2b_structure.sql</code>.</p>
                </div>
            <?php elseif (empty($factures_mois)): ?>
                <div class="compta-blank compta-bl-empty compta-bl-empty--fm">
                    <p><i class="fas fa-file-invoice" aria-hidden="true"></i> Aucune facture mensuelle pour <?php echo htmlspecialchars($periode_label_long); ?>. Générez une facture depuis une <a href="#compta-bl-clients-anchor">fiche client</a> ci-dessus.</p>
                </div>
            <?php else: ?>
                <div class="compta-fm-table-wrap compta-bl-fm-table-wrap">
                    <table class="compta-fm-table" aria-label="Factures mensuelles de la période">
                        <thead>
                            <tr>
                                <th>N° facture</th>
                                <th>Client</th>
                                <th>Statut</th>
                                <th class="compta-fm-table__num">Total HT</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($factures_mois as $f):
                            $st = $f['statut'] ?? '';
                            $st_label = $st === 'brouillon' ? 'Brouillon' : ($st === 'validee' ? 'Validée' : ($st === 'payee' ? 'Payée' : $st));
                            $fid = (int) $f['id'];
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($f['numero_facture'] ?? ''); ?></strong></td>
                                <td><?php echo htmlspecialchars($f['raison_sociale'] ?? '—'); ?></td>
                                <td><span class="compta-badge compta-badge--<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($st_label); ?></span></td>
                                <td class="compta-fm-table__num"><?php echo number_format((float) ($f['total_ht'] ?? 0), 0, ',', ' '); ?> FCFA</td>
                                <td><a href="../devis/facture_mensuelle.php?id=<?php echo $fid; ?>" class="compta-fm-table__link">Ouvrir</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            </div><!-- .compta-bl-body -->
        </div>

        <div id="compta-panel-caisse" class="compta-panel compta-panel--caisse <?php echo $tab_caisse_active ? 'is-active' : ''; ?>" role="tabpanel" aria-labelledby="compta-tab-caisse" <?php echo $tab_caisse_active ? '' : 'hidden'; ?> data-compta-panel="caisse">
            <?php
            $caisse_eyebrow_periode = date('d/m/Y', strtotime($c_date_debut)) . ' – ' . date('d/m/Y', strtotime($c_date_fin));
            ?>
            <div class="compta-hero compta-hero--caisse compta-caisse-hero compta-caisse-hero--premium">
                <div class="compta-hero__copy">
                    <p class="compta-caisse-eyebrow">Caisse magasin · TTC · <?php echo htmlspecialchars($caisse_eyebrow_periode); ?></p>
                    <h2 class="compta-hero__title" id="compta-caisse-hero-title">Caisse magasin</h2>
                    <p class="compta-hero__lead">Consultez les <strong>ventes enregistrées à la caisse</strong> (montants <strong>TTC</strong> par ticket). Filtrez par période, mode de paiement, caissier ou recherche (n° ticket, note).</p>
                    <div class="compta-hero__actions compta-caisse-hero__actions">
                        <a href="../caisse/index.php" class="compta-btn compta-btn--primary"><i class="fas fa-cash-register" aria-hidden="true"></i> Ouvrir la caisse</a>
                    </div>
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

                <?php if (!empty($caisse_totaux['par_mode'])): ?>
                <div class="compta-caisse-modes" aria-label="Répartition par mode de paiement">
                    <?php foreach ($caisse_totaux['par_mode'] as $mk => $mv):
                        if ($mk === '') {
                            continue;
                        }
                        ?>
                    <div class="compta-caisse-mode-chip">
                        <span class="compta-caisse-mode-chip__name"><?php echo htmlspecialchars(caisse_compta_libelle_mode($mk)); ?></span>
                        <span class="compta-caisse-mode-chip__nb"><?php echo (int) $mv['nb']; ?> ticket<?php echo $mv['nb'] > 1 ? 's' : ''; ?></span>
                        <span class="compta-caisse-mode-chip__sum"><?php echo number_format($mv['total'], 0, ',', ' '); ?> FCFA</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="compta-dep-grid">
                    <section class="compta-dep-card compta-dep-card--filters compta-caisse-filters-card" aria-labelledby="compta-caisse-filtres-title">
                        <h3 id="compta-caisse-filtres-title" class="compta-dep-card__title"><i class="fas fa-filter" aria-hidden="true"></i> Filtres</h3>
                        <form method="get" action="index.php" class="compta-dep-filters compta-caisse-filters">
                            <input type="hidden" name="tab" value="caisse">
                            <div class="compta-dep-filters__grid">
                                <div class="compta-dep-filters__field">
                                    <label for="c_date_debut">Du</label>
                                    <input type="date" name="c_date_debut" id="c_date_debut" value="<?php echo htmlspecialchars($c_date_debut); ?>">
                                </div>
                                <div class="compta-dep-filters__field">
                                    <label for="c_date_fin">Au</label>
                                    <input type="date" name="c_date_fin" id="c_date_fin" value="<?php echo htmlspecialchars($c_date_fin); ?>">
                                </div>
                                <div class="compta-dep-filters__field">
                                    <label for="c_mode">Mode de paiement</label>
                                    <select name="c_mode" id="c_mode">
                                        <option value="" <?php echo $c_mode === '' ? 'selected' : ''; ?>>Tous</option>
                                        <option value="especes" <?php echo $c_mode === 'especes' ? 'selected' : ''; ?>>Espèces</option>
                                        <option value="carte" <?php echo $c_mode === 'carte' ? 'selected' : ''; ?>>Carte bancaire</option>
                                        <option value="mobile_money" <?php echo $c_mode === 'mobile_money' ? 'selected' : ''; ?>>Mobile money</option>
                                        <option value="cheque" <?php echo $c_mode === 'cheque' ? 'selected' : ''; ?>>Chèque</option>
                                        <option value="mixte" <?php echo $c_mode === 'mixte' ? 'selected' : ''; ?>>Mixte</option>
                                        <option value="autre" <?php echo $c_mode === 'autre' ? 'selected' : ''; ?>>Autre</option>
                                    </select>
                                </div>
                                <div class="compta-dep-filters__field">
                                    <label for="c_admin">Caissier</label>
                                    <select name="c_admin" id="c_admin">
                                        <option value="0">Tous</option>
                                        <?php foreach ($caisse_admins_filtre as $adm): ?>
                                            <option value="<?php echo (int) $adm['id']; ?>" <?php echo $c_admin === (int) $adm['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(trim(($adm['prenom'] ?? '') . ' ' . ($adm['nom'] ?? '')) ?: ($adm['email'] ?? '#' . $adm['id'])); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="compta-dep-filters__field compta-dep-filters__field--full">
                                    <label for="c_q">Recherche (n° ticket, note)</label>
                                    <input type="search" name="c_q" id="c_q" value="<?php echo htmlspecialchars($c_q); ?>" placeholder="Ex. TKT2026…" autocomplete="off">
                                </div>
                            </div>
                            <div class="compta-dep-filters__actions">
                                <button type="submit" class="compta-dep-filters__btn"><i class="fas fa-search" aria-hidden="true"></i> Appliquer</button>
                                <a href="index.php?tab=caisse" class="compta-dep-filters__reset">Réinitialiser</a>
                            </div>
                        </form>
                    </section>
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
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($caisse_ventes_liste as $cv):
                                    $vid = (int) ($cv['id'] ?? 0);
                                    $dt = $cv['date_vente'] ?? '';
                                    $dt_fr = $dt !== '' ? date('d/m/Y H:i', strtotime($dt)) : '—';
                                    $adm_nom = trim(($cv['admin_prenom'] ?? '') . ' ' . ($cv['admin_nom'] ?? ''));
                                    if ($adm_nom === '') {
                                        $adm_nom = '—';
                                    }
                                    $mode_lbl = caisse_compta_libelle_mode($cv['mode_paiement'] ?? '');
                                    ?>
                                <tr>
                                    <td data-label="Date"><time datetime="<?php echo htmlspecialchars(substr($dt, 0, 19)); ?>"><?php echo htmlspecialchars($dt_fr); ?></time></td>
                                    <td data-label="Ticket"><strong><?php echo htmlspecialchars($cv['numero_ticket'] ?? ''); ?></strong></td>
                                    <td data-label="Caissier"><?php echo htmlspecialchars($adm_nom); ?></td>
                                    <td data-label="Paiement"><span class="compta-caisse-badge"><?php echo htmlspecialchars($mode_lbl); ?></span></td>
                                    <td class="compta-caisse-table__num" data-label="TTC"><?php echo number_format((float) ($cv['montant_total'] ?? 0), 0, ',', ' '); ?> <span class="compta-caisse-cur">FCFA</span></td>
                                    <td class="compta-caisse-table__act" data-label="">
                                        <a href="../caisse/index.php?ticket=<?php echo $vid; ?>" class="compta-caisse-link">Voir le ticket <i class="fas fa-external-link-alt" aria-hidden="true"></i></a>
                                    </td>
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
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
