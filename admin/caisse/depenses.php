<?php
/**
 * Caissier : saisie et suivi des dépenses / charges (HT, TVA)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_saisir_depenses_caisse()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_depenses.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$admin_id = (int) ($_SESSION['admin_id'] ?? 0);
$depenses_ok = depenses_tables_ok();
$depense_flash_ok = isset($_GET['dep_ok']) && $_GET['dep_ok'] === '1';
$depense_error_msg = '';

if ($depenses_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['depense_ajout'])) {
    $tok = $_POST['csrf_token'] ?? '';
    if (!hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), (string) $tok)) {
        $depense_error_msg = 'Session expirée. Rechargez la page.';
    } else {
        $r = process_depense_ajout($_POST, $admin_id);
        if (!empty($r['success'])) {
            $redir = [
                'dep_ok' => '1',
                'd_periode' => isset($_POST['filtre_d_periode']) ? trim((string) $_POST['filtre_d_periode']) : 'jour',
                'd_ref' => isset($_POST['filtre_d_ref']) ? trim((string) $_POST['filtre_d_ref']) : date('Y-m-d'),
                'd_date_debut' => isset($_POST['filtre_d_date_debut']) ? trim((string) $_POST['filtre_d_date_debut']) : '',
                'd_date_fin' => isset($_POST['filtre_d_date_fin']) ? trim((string) $_POST['filtre_d_date_fin']) : '',
            ];
            header('Location: depenses.php?' . http_build_query($redir));
            exit;
        }
        $depense_error_msg = $r['message'] ?? 'Erreur lors de l\'enregistrement.';
    }
}

$d_periode = isset($_GET['d_periode']) ? trim((string) $_GET['d_periode']) : 'jour';
if (!in_array($d_periode, ['jour', 'semaine', 'plage'], true)) {
    $d_periode = 'jour';
}
$d_ref = isset($_GET['d_ref']) ? trim((string) $_GET['d_ref']) : '';
if ($d_ref === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d_ref)) {
    $d_ref = date('Y-m-d');
} else {
    $d_pxr = explode('-', $d_ref);
    if (count($d_pxr) !== 3 || !checkdate((int) $d_pxr[1], (int) $d_pxr[2], (int) $d_pxr[0])) {
        $d_ref = date('Y-m-d');
    }
}
$d_plage_debut_raw = isset($_GET['d_date_debut']) ? trim((string) $_GET['d_date_debut']) : '';
$d_plage_fin_raw = isset($_GET['d_date_fin']) ? trim((string) $_GET['d_date_fin']) : '';

$d_range = depenses_compute_date_range($d_periode, $d_ref, $d_plage_debut_raw, $d_plage_fin_raw);
$d_date_debut = $d_range[0];
$d_date_fin = $d_range[1];
$d_depenses_periode_label = depenses_libelle_periode_filtre($d_periode, $d_date_debut, $d_date_fin);

/** Affichage filtre date jj/mm/aaaa (valeur filtre = Y-m-d) */
$d_ref_fr = date('d/m/Y', strtotime($d_ref));

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
        'admin_createur_id' => $admin_id,
    ]);
    $totaux_dep = depenses_calculer_totaux($depenses_liste);
}

$page_title = 'Dépenses caisse';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/compta-depenses.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-dashboard-caisse-pages.css<?php echo asset_version_query(); ?>">
    <style>
        .page-caisse-depenses .content-header { margin-bottom: 16px; }
        .page-caisse-depenses .caisse-dep-split { display: grid; gap: 20px; }
        @media (min-width: 960px) {
            .page-caisse-depenses .caisse-dep-split:not(.caisse-dep-split--liste-seule) {
                grid-template-columns: 1fr 1fr;
                align-items: start;
            }
        }
        .caisse-dep-form-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            padding: 20px;
            box-shadow: var(--glass-shadow);
        }
        .caisse-dep-form-card h2 { margin: 0 0 8px; font-size: 1.1rem; color: var(--titres); }
        .caisse-dep-form-card .compta-dep-form__row { margin-bottom: 14px; }
        .caisse-dep-form-card label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.9rem; }
        .caisse-dep-form-card input[type="text"],
        .caisse-dep-form-card input[type="date"],
        .caisse-dep-form-card select,
        .caisse-dep-form-card textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-input);
            border-radius: 8px;
            font-size: 0.95rem;
        }
        .caisse-dep-form-card .compta-dep-form__row--2 { display: grid; gap: 12px; grid-template-columns: 1fr 1fr; }
        @media (max-width: 520px) {
            .caisse-dep-form-card .compta-dep-form__row--2 { grid-template-columns: 1fr; }
        }
        .caisse-dep-form-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 8px; }
        .caisse-dep-form-actions .btn-primary { border: none; cursor: pointer; padding: 10px 16px; border-radius: 8px; font-weight: 600; }
        .compta-dep-periode-hint { margin: 0 0 16px; font-size: 0.9rem; color: var(--gris-fonce); }
        .caisse-dep-list-block { min-width: 0; }
        .caisse-dep-split--liste-seule {
            grid-template-columns: 1fr;
            max-width: 900px;
            position: relative;
        }
        .caisse-dep-add-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .caisse-dep-form-overlay {
            position: fixed;
            inset: 0;
            z-index: 1200;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 24px 16px 40px;
            overflow: auto;
            box-sizing: border-box;
        }
        .caisse-dep-form-overlay[hidden] {
            display: none !important;
        }
        .caisse-dep-form-overlay__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(13, 13, 13, 0.45);
            backdrop-filter: blur(2px);
        }
        .caisse-dep-form-card--modal {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            margin-top: min(8vh, 48px);
            max-height: calc(100vh - 48px);
            overflow: auto;
        }
        .caisse-dep-form-card--modal .caisse-dep-form-close {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            background: var(--blanc-neige, #f5f5f5);
            color: var(--titres);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        .caisse-dep-form-card--modal .caisse-dep-form-close:hover {
            background: var(--bleu-pale, rgba(53, 100, 166, 0.12));
        }
        .caisse-dep-form-card--modal h2 {
            padding-right: 44px;
        }
        body.caisse-dep-form-open {
            overflow: hidden;
        }
    </style>
</head>
<body class="admin-caisse-page page-caisse-depenses">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-wallet" aria-hidden="true"></i> <?php echo htmlspecialchars($page_title); ?></h1>
        <div class="header-actions">
            <?php if ($depenses_ok): ?>
            <button type="button" class="btn-primary" id="btnCaisseDepOuvrirForm">
                <i class="fas fa-plus-circle" aria-hidden="true"></i> Ajouter une dépense
            </button>
            <?php endif; ?>
            <a href="encaisser-ticket.php" class="btn-back"><i class="fas fa-money-bill-wave"></i> Encaissement</a>
            <a href="historique-encaissements.php" class="btn-back"><i class="fas fa-history"></i> Historique</a>
        </div>
    </div>

    <?php if ($depense_flash_ok): ?>
        <div class="message success"><i class="fas fa-check-circle"></i> Dépense enregistrée.</div>
    <?php endif; ?>
    <?php if ($depense_error_msg !== ''): ?>
        <div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($depense_error_msg); ?></div>
    <?php endif; ?>

    <?php if (!$depenses_ok): ?>
        <p class="message error"><i class="fas fa-database"></i> Tables absentes : exécutez la migration <code>migrations/create_depenses_compta.sql</code>.</p>
    <?php else: ?>
        <div class="compta-hero compta-hero--depenses compta-dep-hero compta-dep-hero--premium" style="margin-bottom:20px;">
            <div class="compta-hero__copy">
                <p class="compta-dep-eyebrow">Charges &amp; suivi</p>
                <h2 class="compta-hero__title">Saisie des dépenses</h2>
                <p class="compta-hero__lead">Enregistrez les charges en <strong>HT</strong>, avec ou sans <strong>TVA</strong>. Les montants sont en FCFA. La comptabilité consulte l’ensemble des saisies dans l’onglet <strong>Dépenses</strong>.</p>
            </div>
        </div>

        <div id="caisseDepFormOverlay" class="caisse-dep-form-overlay" hidden aria-hidden="true">
            <div class="caisse-dep-form-overlay__backdrop" data-dep-form-close tabindex="-1"></div>
            <div class="caisse-dep-form-card caisse-dep-form-card--modal" role="dialog" aria-modal="true" aria-labelledby="caisseDepFormTitle">
                <button type="button" class="caisse-dep-form-close" data-dep-form-close aria-label="Fermer le formulaire"><i class="fas fa-times" aria-hidden="true"></i></button>
                <h2 id="caisseDepFormTitle"><i class="fas fa-plus-circle" aria-hidden="true"></i> Nouvelle dépense</h2>
                <form method="post" action="depenses.php" class="compta-dep-form" id="caisseDepFormNouvelle">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                    <input type="hidden" name="depense_ajout" value="1">
                    <input type="hidden" name="filtre_d_periode" value="<?php echo htmlspecialchars($d_periode); ?>">
                    <input type="hidden" name="filtre_d_ref" value="<?php echo htmlspecialchars($d_ref); ?>">
                    <input type="hidden" name="filtre_d_date_debut" value="<?php echo htmlspecialchars($d_date_debut); ?>">
                    <input type="hidden" name="filtre_d_date_fin" value="<?php echo htmlspecialchars($d_date_fin); ?>">
                    <div class="compta-dep-form__row">
                        <label for="dep_libelle">Libellé <span class="req">*</span></label>
                        <input type="text" name="libelle" id="dep_libelle" required maxlength="255" placeholder="Ex. Achat fournitures" autocomplete="off">
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
                        <select name="type_depense" id="dep_type" data-caisse-dep-type>
                            <option value="sans_tva">Hors TVA (HT = TTC)</option>
                            <option value="avec_tva" selected>Avec TVA (HT + TVA = TTC)</option>
                        </select>
                    </div>
                    <div class="compta-dep-form__row compta-dep-form__row--2">
                        <div>
                            <label for="dep_ht">Montant HT (FCFA) <span class="req">*</span></label>
                            <input type="text" name="montant_ht" id="dep_ht" required inputmode="decimal" placeholder="0" pattern="[0-9]+([.,][0-9]+)?">
                        </div>
                        <div class="compta-dep-wrap-taux" id="caisse-dep-wrap-taux">
                            <label for="dep_tva">TVA (%)</label>
                            <input type="text" name="taux_tva" id="dep_tva" inputmode="decimal" placeholder="20" value="20">
                        </div>
                    </div>
                    <div class="compta-dep-form__row">
                        <label for="dep_notes">Notes</label>
                        <textarea name="notes" id="dep_notes" rows="2" placeholder="Réf. facture…"></textarea>
                    </div>
                    <div class="caisse-dep-form-actions">
                        <button type="button" class="btn-secondary" data-dep-form-close>Annuler</button>
                        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="caisse-dep-split caisse-dep-split--liste-seule">
            <div class="caisse-dep-list-block">
                <form method="get" action="depenses.php" id="caisse-dep-filtres-form" class="compta-ventes-filter" aria-label="Filtrer vos dépenses">
                    <div class="compta-ventes-filter__row">
                        <label for="caisse-d-periode" class="compta-ventes-filter__label">Période</label>
                        <div class="compta-ventes-filter__controls">
                            <select name="d_periode" id="caisse-d-periode" class="compta-ventes-filter__select">
                                <option value="jour" <?php echo $d_periode === 'jour' ? 'selected' : ''; ?>>Un jour</option>
                                <option value="semaine" <?php echo $d_periode === 'semaine' ? 'selected' : ''; ?>>Une semaine</option>
                                <option value="plage" <?php echo $d_periode === 'plage' ? 'selected' : ''; ?>>Période (du … au …)</option>
                            </select>
                            <button type="submit" class="compta-ventes-filter__btn"><i class="fas fa-filter"></i> Afficher</button>
                            <a href="depenses.php" class="compta-ventes-filter__reset">Aujourd’hui</a>
                        </div>
                    </div>
                    <div id="caisse-wrap-d-jour" class="compta-ventes-filter__panel compta-ventes-filter__fields <?php echo $d_periode === 'jour' ? '' : 'is-hidden'; ?>">
                        <label for="d_ref_jour_c">Date (jj/mm/aaaa)</label>
                        <input type="text" id="d_ref_jour_c" class="compta-ventes-filter__date compta-ventes-filter__date--fr" value="<?php echo htmlspecialchars($d_ref_fr); ?>" placeholder="jj/mm/aaaa" inputmode="numeric" autocomplete="off" maxlength="10">
                        <input type="hidden" name="d_ref" id="d_ref_jour_hidden" value="<?php echo htmlspecialchars($d_ref); ?>">
                    </div>
                    <div id="caisse-wrap-d-semaine" class="compta-ventes-filter__panel compta-ventes-filter__fields <?php echo $d_periode === 'semaine' ? '' : 'is-hidden'; ?>">
                        <label for="d_ref_sem_c">Semaine contenant le (jj/mm/aaaa)</label>
                        <input type="text" id="d_ref_sem_c" class="compta-ventes-filter__date compta-ventes-filter__date--fr" value="<?php echo htmlspecialchars($d_ref_fr); ?>" placeholder="jj/mm/aaaa" inputmode="numeric" autocomplete="off" maxlength="10">
                        <input type="hidden" name="d_ref" id="d_ref_sem_hidden" value="<?php echo htmlspecialchars($d_ref); ?>">
                    </div>
                    <div id="caisse-wrap-d-plage" class="compta-ventes-filter__panel compta-ventes-filter__fields compta-ventes-filter__fields--plage <?php echo $d_periode === 'plage' ? '' : 'is-hidden'; ?>">
                        <div>
                            <label for="d_date_debut_c">Du</label>
                            <input type="date" name="d_date_debut" id="d_date_debut_c" class="compta-ventes-filter__date" value="<?php echo htmlspecialchars($d_date_debut); ?>">
                        </div>
                        <div>
                            <label for="d_date_fin_c">Au</label>
                            <input type="date" name="d_date_fin" id="d_date_fin_c" class="compta-ventes-filter__date" value="<?php echo htmlspecialchars($d_date_fin); ?>">
                        </div>
                    </div>
                    <div class="compta-ventes-filter__panel compta-ventes-filter__fields" style="margin-top:12px; flex-wrap:wrap; gap:12px;">
                        <div class="compta-dep-filters__field">
                            <label for="d_categorie_c">Catégorie</label>
                            <select name="d_categorie" id="d_categorie_c">
                                <option value="0">Toutes</option>
                                <?php foreach ($categories_dep as $cat): ?>
                                    <option value="<?php echo (int) $cat['id']; ?>" <?php echo $d_categorie === (int) $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="compta-dep-filters__field">
                            <label for="d_type_c">TVA</label>
                            <select name="d_type" id="d_type_c">
                                <option value="" <?php echo $d_type_dep === '' ? 'selected' : ''; ?>>Tous</option>
                                <option value="sans_tva" <?php echo $d_type_dep === 'sans_tva' ? 'selected' : ''; ?>>Sans TVA</option>
                                <option value="avec_tva" <?php echo $d_type_dep === 'avec_tva' ? 'selected' : ''; ?>>Avec TVA</option>
                            </select>
                        </div>
                        <div class="compta-dep-filters__field compta-dep-filters__field--full" style="flex:1; min-width:200px;">
                            <label for="d_q_c">Libellé</label>
                            <input type="search" name="d_q" id="d_q_c" value="<?php echo htmlspecialchars($d_q); ?>" placeholder="Recherche…">
                        </div>
                    </div>
                </form>

                <div class="compta-dep-kpis" style="margin-top:16px;" aria-label="Synthèse">
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
                <p class="compta-dep-periode-hint"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($d_depenses_periode_label); ?> · <strong>Vos saisies uniquement</strong></p>

                <?php if (empty($depenses_liste)): ?>
                    <div class="compta-dep-empty">
                        <i class="fas fa-inbox" aria-hidden="true"></i>
                        <p>Aucune dépense sur cette période.</p>
                    </div>
                <?php else: ?>
                    <div class="compta-dep-list" role="list">
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
                                <i class="fas fa-folder-open"></i> <?php echo htmlspecialchars($row['categorie_nom'] ?? '—'); ?>
                            </p>
                            <dl class="compta-dep-item__amounts">
                                <div class="compta-dep-item__amt"><dt>HT</dt><dd><span class="compta-dep-item__num"><?php echo $ht; ?></span> FCFA</dd></div>
                                <div class="compta-dep-item__amt"><dt>TVA</dt><dd><span class="compta-dep-item__num<?php echo $tva_show ? '' : ' compta-dep-item__num--muted'; ?>"><?php echo htmlspecialchars($tva); ?></span><?php if ($tva_show): ?> FCFA<?php endif; ?></dd></div>
                                <div class="compta-dep-item__amt compta-dep-item__amt--ttc"><dt>TTC</dt><dd><span class="compta-dep-item__num compta-dep-item__num--ttc"><?php echo $ttc; ?></span> FCFA</dd></div>
                            </dl>
                        </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        (function () {
            function pad2(n) { return n < 10 ? '0' + n : String(n); }
            function dateFrToIso(s) {
                s = (s || '').trim().replace(/\s/g, '');
                var m = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
                if (!m) return null;
                var d = parseInt(m[1], 10), mo = parseInt(m[2], 10), y = parseInt(m[3], 10);
                if (mo < 1 || mo > 12 || d < 1 || d > 31) return null;
                var dt = new Date(y, mo - 1, d);
                if (dt.getFullYear() !== y || dt.getMonth() !== mo - 1 || dt.getDate() !== d) return null;
                return y + '-' + pad2(mo) + '-' + pad2(d);
            }
            function isoToDateFr(iso) {
                var p = (iso || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})$/);
                if (!p) return '';
                return p[3] + '/' + p[2] + '/' + p[1];
            }

            var overlay = document.getElementById('caisseDepFormOverlay');
            var btnOpen = document.getElementById('btnCaisseDepOuvrirForm');
            function closeDepForm() {
                if (!overlay) return;
                overlay.setAttribute('hidden', 'hidden');
                overlay.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('caisse-dep-form-open');
            }
            function openDepForm() {
                if (!overlay) return;
                overlay.removeAttribute('hidden');
                overlay.setAttribute('aria-hidden', 'false');
                document.body.classList.add('caisse-dep-form-open');
                var lib = document.getElementById('dep_libelle');
                if (lib) setTimeout(function () { lib.focus(); }, 50);
            }
            if (btnOpen) btnOpen.addEventListener('click', openDepForm);
            if (overlay) {
                overlay.querySelectorAll('[data-dep-form-close]').forEach(function (el) {
                    el.addEventListener('click', closeDepForm);
                });
            }
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && overlay && !overlay.hasAttribute('hidden')) {
                    closeDepForm();
                    e.preventDefault();
                }
            });

            var sel = document.getElementById('dep_type');
            var wrap = document.getElementById('caisse-dep-wrap-taux');
            if (sel && wrap) {
                function syncTva() {
                    var on = sel.value === 'avec_tva';
                    wrap.style.display = on ? '' : 'none';
                    wrap.querySelectorAll('input').forEach(function (inp) { inp.disabled = !on; });
                }
                sel.addEventListener('change', syncTva);
                syncTva();
            }
            var ps = document.getElementById('caisse-d-periode');
            var ff = document.getElementById('caisse-dep-filtres-form');
            function syncFilterRefFromDisplay() {
                var jIn = document.getElementById('d_ref_jour_c');
                var jH = document.getElementById('d_ref_jour_hidden');
                if (jIn && jH) {
                    var isoJ = dateFrToIso(jIn.value);
                    if (isoJ) jH.value = isoJ;
                }
                var sIn = document.getElementById('d_ref_sem_c');
                var sH = document.getElementById('d_ref_sem_hidden');
                if (sIn && sH) {
                    var isoS = dateFrToIso(sIn.value);
                    if (isoS) sH.value = isoS;
                }
            }
            function toggleCaisseDep(p) {
                var map = { jour: 'caisse-wrap-d-jour', semaine: 'caisse-wrap-d-semaine', plage: 'caisse-wrap-d-plage' };
                Object.keys(map).forEach(function (k) {
                    var el = document.getElementById(map[k]);
                    if (!el) return;
                    el.classList.toggle('is-hidden', k !== p);
                });
                syncFilterRefFromDisplay();
            }
            if (ps) ps.addEventListener('change', function () { toggleCaisseDep(this.value); });
            [['d_ref_jour_c', 'd_ref_jour_hidden'], ['d_ref_sem_c', 'd_ref_sem_hidden']].forEach(function (pair) {
                var vis = document.getElementById(pair[0]);
                var hid = document.getElementById(pair[1]);
                if (!vis || !hid) return;
                vis.addEventListener('blur', function () {
                    var iso = dateFrToIso(vis.value);
                    if (iso) {
                        vis.value = isoToDateFr(iso);
                        hid.value = iso;
                    }
                });
            });
            if (ff) {
                ff.addEventListener('submit', function (ev) {
                    syncFilterRefFromDisplay();
                    var per = ps ? ps.value : 'jour';
                    var err = '';
                    if (per === 'jour') {
                        var jIn = document.getElementById('d_ref_jour_c');
                        if (jIn && !dateFrToIso(jIn.value)) err = 'Date invalide : utilisez le format jj/mm/aaaa.';
                    } else if (per === 'semaine') {
                        var sIn = document.getElementById('d_ref_sem_c');
                        if (sIn && !dateFrToIso(sIn.value)) err = 'Date invalide : utilisez le format jj/mm/aaaa.';
                    }
                    if (err) {
                        ev.preventDefault();
                        window.alert(err);
                        return;
                    }
                    ff.querySelectorAll('.compta-ventes-filter__panel').forEach(function (panel) {
                        var hide = panel.classList.contains('is-hidden');
                        panel.querySelectorAll('input, select').forEach(function (inp) { inp.disabled = hide; });
                    });
                });
            }
        })();
        </script>
    <?php endif; ?>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
