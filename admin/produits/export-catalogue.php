<?php
/**
 * Export catalogue produits — aperçu par période + filtres.
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../includes/export_produits_catalogue_pdf.php';
require_once __DIR__ . '/../../includes/export_catalogue_job.php';
require_once __DIR__ . '/../../includes/export_catalogue_suivi.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['admin_csrf'];

$flash_message = '';
$flash_type = '';
if (isset($_SESSION['export_catalogue_flash'])) {
    $flash = $_SESSION['export_catalogue_flash'];
    unset($_SESSION['export_catalogue_flash']);
    if (is_array($flash)) {
        $flash_type = (string) ($flash['type'] ?? '');
        $flash_message = (string) ($flash['message'] ?? '');
    }
}

$prix_draft = export_catalogue_prix_draft_get();
if ($flash_type === 'ok') {
    export_catalogue_prix_draft_clear();
    $prix_draft = [];
}

$has_marque_filtre = produits_has_column('marque_id');
$marques_filtre = [];
$categories = get_all_categories();

if ($has_marque_filtre) {
    require_once __DIR__ . '/../../models/model_marques.php';
    if (marques_table_ok()) {
        $marques_filtre = get_all_marques_ordered_by_nom();
    }
}

export_catalogue_filters_handle_reset();
export_catalogue_filters_restore_redirect_if_needed();

$filters = export_catalogue_filters_from_request($_GET);
export_catalogue_filters_save_session($filters);
$date_debut = $filters['date_debut'];
$date_fin = $filters['date_fin'];
$mode = $filters['mode'];
$recherche = $filters['recherche'];
$categorie_id = $filters['categorie_id'];
$marque_id = $filters['marque_id'];
$fournisseur_id = 0;
$date_debut_fr = export_catalogue_format_date_input_fr($date_debut);
$date_fin_fr = export_catalogue_format_date_input_fr($date_fin);

$export_preview_per_page = 30;
$page = $filters['page'];

$total_export = count_admin_produits_export_catalogue($date_debut, $date_fin, $mode, $recherche, $categorie_id, $marque_id, $fournisseur_id);
$total_pages = max(1, (int) ceil($total_export / $export_preview_per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $export_preview_per_page;
$produits_export = get_admin_produits_export_catalogue(
    $date_debut,
    $date_fin,
    $mode,
    $recherche,
    $categorie_id,
    $marque_id,
    $fournisseur_id,
    $export_preview_per_page,
    $offset
);

$pagination_query_base = [
    'date_debut' => $date_debut_fr,
    'date_fin' => $date_fin_fr,
    'mode' => $mode,
    'recherche' => $recherche,
    'categorie_id' => $categorie_id,
    'marque_id' => $marque_id,
];

$pdf_query_base = http_build_query([
    'date_debut' => $date_debut_fr,
    'date_fin' => $date_fin_fr,
    'mode' => $mode,
    'recherche' => $recherche,
    'categorie_id' => $categorie_id,
    'marque_id' => $marque_id,
]);

$filtres_form_classes = 'admin-filters-bar page-produits-export-filters';
if (!empty($marques_filtre)) {
    $filtres_form_classes .= ' page-produits-export-filters--has-marque';
}

$mode_labels = export_catalogue_pdf_mode_labels();
$export_use_async_pdf = $total_export >= EXPORT_CATALOGUE_ASYNC_MIN;
$export_has_prix_achat = export_catalogue_has_prix_achat_column();
$pdf_columns_catalog = export_catalogue_pdf_columns_catalog($export_has_prix_achat);
$pdf_columns_defs = export_catalogue_pdf_columns_definitions();
$suivi_columns_catalog = export_catalogue_suivi_columns_catalog();
$suivi_columns_defs = export_catalogue_suivi_columns_definitions();
$suivi_visible_cols = export_catalogue_suivi_colonnes_resolved((int) $_SESSION['admin_id']);
$suivi_visible_lookup = array_fill_keys($suivi_visible_cols, true);
$save_redirect_query = http_build_query(array_merge($pagination_query_base, ['page' => $page]));
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi du catalogue — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-produits-index.css'); ?>
    <?php fpl_css_link('admin-produits-export.css'); ?>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="page-produits-admin page-produits-export" data-export-total="<?php echo (int) $total_export; ?>"
        data-export-async-min="<?php echo (int) EXPORT_CATALOGUE_ASYNC_MIN; ?>"
        data-export-use-async="<?php echo $export_use_async_pdf ? '1' : '0'; ?>"
        data-export-prix-save="<?php echo htmlspecialchars($flash_type, ENT_QUOTES, 'UTF-8'); ?>"
        data-suivi-visible-cols="<?php echo htmlspecialchars(json_encode($suivi_visible_cols, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
        data-suivi-catalog-cols="<?php echo htmlspecialchars(json_encode($suivi_columns_catalog, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
        data-suivi-csrf="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="content-header dashboard-hero page-produits-hero">
            <div class="dashboard-hero-text">
                <p class="dashboard-eyebrow">Catalogue boutique</p>
                <h1><i class="fas fa-clipboard-list" aria-hidden="true"></i> Suivi du catalogue</h1>

                <?php if ($flash_message !== ''): ?>
                <div class="message <?php echo $flash_type === 'ok' ? 'success' : 'error'; ?> page-produits-flash" role="status">
                    <i class="fas fa-<?php echo $flash_type === 'ok' ? 'check-circle' : 'exclamation-circle'; ?>" aria-hidden="true"></i>
                    <?php echo htmlspecialchars($flash_message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>

                <div class="page-produits-export-progress" id="exportCataloguePdfProgress" hidden
                    role="status" aria-live="polite" aria-busy="false">
                    <div class="page-produits-export-progress__row">
                        <p class="page-produits-export-progress__status" id="exportCataloguePdfStatus">
                            Initialisation…
                        </p>
                        <span class="page-produits-export-progress__percent" id="exportCataloguePdfPercent">0&nbsp;%</span>
                    </div>
                    <div class="page-produits-export-progress__track">
                        <div class="page-produits-export-progress__bar" id="exportCataloguePdfBar" style="width:0%"></div>
                    </div>
                    <div class="page-produits-export-progress__actions">
                        <button type="button" class="btn-secondary page-produits-export-progress__cancel"
                            id="exportCataloguePdfCancel">
                            <i class="fas fa-times" aria-hidden="true"></i> Annuler
                        </button>
                    </div>
                </div>

                <div class="page-produits-hero__actions" id="exportCataloguePdfHeroActions">

                    <a href="index.php" class="btn-secondary page-produits-hero__btn">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour à la liste
                    </a>
                    <?php if ($total_export > 0): ?>
                    <button type="button"
                        class="btn-primary page-produits-hero__btn page-produits-export-pdf-btn"
                        data-export-pdf-trigger="1"
                        data-export-query="<?php echo htmlspecialchars($pdf_query_base, ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fas fa-download" aria-hidden="true"></i> Télécharger le PDF
                        (<?php echo (int) $total_export; ?>)
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <section class="produits-section page-produits-section page-produits-export-section"
            aria-labelledby="export-section-heading">
            <div class="section-title page-produits-section__head">
                <h2 id="export-section-heading"><i class="fas fa-filter" aria-hidden="true"></i> Filtres du suivi
                    <span class="page-produits-count" aria-live="polite">(<?php echo (int) $total_export; ?>)</span>
                </h2>
            </div>

            <form method="GET" action=""
                class="<?php echo htmlspecialchars($filtres_form_classes, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="admin-filter-field page-produits-export-filters__period">
                    <label for="date_debut">Du (jj/mm/aaaa)</label>
                    <input type="text" id="date_debut" name="date_debut" class="page-produits-export-date"
                        value="<?php echo htmlspecialchars($date_debut_fr, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="jj/mm/aaaa" inputmode="numeric" maxlength="10" required
                        pattern="(0[1-9]|[12][0-9]|3[01])/(0[1-9]|1[0-2])/[0-9]{4}">
                </div>
                <div class="admin-filter-field page-produits-export-filters__period">
                    <label for="date_fin">Au (jj/mm/aaaa)</label>
                    <input type="text" id="date_fin" name="date_fin" class="page-produits-export-date"
                        value="<?php echo htmlspecialchars($date_fin_fr, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="jj/mm/aaaa" inputmode="numeric" maxlength="10" required
                        pattern="(0[1-9]|[12][0-9]|3[01])/(0[1-9]|1[0-2])/[0-9]{4}">
                </div>
                <div class="admin-filter-field page-produits-export-filters__mode">
                    <label for="mode">Type</label>
                    <select id="mode" name="mode">
                        <?php foreach ($mode_labels as $k => $label): ?>
                        <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo $mode === $k ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-filter-field page-produits-export-filters__search">
                    <label for="recherche">Recherche</label>
                    <input type="text" id="recherche" name="recherche" placeholder="Nom, description, FPL, marque…"
                        value="<?php echo htmlspecialchars($recherche, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off"
                        inputmode="search">
                </div>
                <div class="admin-filter-field page-produits-export-filters__categorie">
                    <label for="categorie_id">Catégorie</label>
                    <select id="categorie_id" name="categorie_id">
                        <option value="0">Toutes les catégories</option>
                        <?php foreach ($categories as $categorie): ?>
                        <option value="<?php echo (int) $categorie['id']; ?>"
                            <?php echo $categorie_id === (int) $categorie['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($categorie['nom']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!empty($marques_filtre)): ?>
                <div class="admin-filter-field page-produits-export-filters__marque">
                    <label for="marque_id">Marque</label>
                    <select id="marque_id" name="marque_id">
                        <option value="0">Toutes les marques</option>
                        <?php foreach ($marques_filtre as $marque): ?>
                        <option value="<?php echo (int) $marque['id']; ?>"
                            <?php echo $marque_id === (int) $marque['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($marque['nom']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="admin-filter-actions page-produits-export-filters__actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Afficher
                    </button>
                    <a href="export-catalogue.php?reset=1" class="btn-filter-reset">
                        <i class="fas fa-rotate-left"></i>&nbsp;Aujourd’hui
                    </a>
                    <button type="button" class="btn-secondary page-produits-export-options-btn"
                        id="exportCatalogueTableOptionsBtn" data-suivi-table-options-trigger="1">
                        <i class="fas fa-sliders" aria-hidden="true"></i> Options
                    </button>
                    <?php if ($total_export > 0): ?>
                    <button type="button"
                        class="btn-primary btn-export-pdf-inline page-produits-export-pdf-btn"
                        data-export-pdf-trigger="1"
                        data-export-query="<?php echo htmlspecialchars($pdf_query_base, ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($total_export === 0): ?>
            <div class="empty-state page-produits-empty">
                <div class="page-produits-empty__icon" aria-hidden="true"><i class="fas fa-inbox"></i></div>
                <p class="page-produits-empty__title">Aucun produit pour cette période</p>
                <p class="page-produits-empty__hint">Modifiez les dates, le type, la recherche ou les filtres catégorie / marque.</p>
            </div>
            <?php else: ?>
            <div id="page-produits-export-wrap">
                <form method="post" action="export-catalogue-save-prix.php" class="page-produits-export-save-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="redirect_query" value="<?php echo htmlspecialchars($save_redirect_query, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="page-produits-export-save-bar">
                        <p class="page-produits-export-save-bar__hint">
                            <i class="fas fa-pen-to-square" aria-hidden="true"></i>
                            Modifiez les prix achat / vente directement dans le tableau puis enregistrez.
                        </p>
                        <button type="submit" class="btn-primary page-produits-export-save-bar__btn">
                            <i class="fas fa-save" aria-hidden="true"></i> Enregistrer les prix
                        </button>
                    </div>
                <div class="page-produits-export-table-wrap">
                    <table class="page-produits-export-table" id="exportCatalogueSuiviTable" aria-label="Aperçu suivi catalogue">
                        <colgroup>
                            <?php foreach ($suivi_columns_catalog as $col_key => $col_label): ?>
                            <?php
                            $def = $suivi_columns_defs[$col_key] ?? [];
                            $hidden = !isset($suivi_visible_lookup[$col_key]);
                            $col_style = export_catalogue_suivi_col_style_attr($col_key, $hidden);
                            ?>
                            <col class="<?php echo htmlspecialchars((string) ($def['css_col'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><?php echo $hidden ? ' is-suivi-col-hidden' : ''; ?>"
                                data-suivi-col="<?php echo htmlspecialchars($col_key, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $col_style !== '' ? ' style="' . htmlspecialchars($col_style, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
                            <?php endforeach; ?>
                        </colgroup>
                        <thead>
                            <tr>
                                <?php foreach ($suivi_columns_catalog as $col_key => $col_label): ?>
                                <?php
                                $def = $suivi_columns_defs[$col_key] ?? [];
                                $hidden = !isset($suivi_visible_lookup[$col_key]);
                                $th_class = !empty($def['num']) ? 'page-produits-export-table__num' : '';
                                if ($col_key === 'img') {
                                    $th_class .= ($th_class !== '' ? ' ' : '') . 'page-produits-export-table__img';
                                }
                                if ($hidden) {
                                    $th_class .= ($th_class !== '' ? ' ' : '') . 'is-suivi-col-hidden';
                                }
                                ?>
                                <th scope="col"<?php echo $th_class !== '' ? ' class="' . htmlspecialchars(trim($th_class), ENT_QUOTES, 'UTF-8') . '"' : ''; ?>
                                    data-suivi-col="<?php echo htmlspecialchars($col_key, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($col_label, ENT_QUOTES, 'UTF-8'); ?>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produits_export as $produit): ?>
                            <?php
                            $pid = (int) ($produit['id'] ?? 0);
                            $cell_ctx = [
                                'pid' => $pid,
                                'visible_cols' => $suivi_visible_cols,
                                'prix_draft' => $prix_draft,
                            ];
                            ?>
                            <tr>
                                <?php foreach ($suivi_columns_catalog as $col_key => $col_label): ?>
                                <?php
                                $def = $suivi_columns_defs[$col_key] ?? [];
                                $hidden = !isset($suivi_visible_lookup[$col_key]);
                                $td_classes = trim((string) ($def['css_cell'] ?? ''));
                                if ($hidden) {
                                    $td_classes .= ($td_classes !== '' ? ' ' : '') . 'is-suivi-col-hidden';
                                }
                                ?>
                                <td<?php echo $td_classes !== '' ? ' class="' . htmlspecialchars($td_classes, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>
                                    data-suivi-col="<?php echo htmlspecialchars($col_key, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo export_catalogue_suivi_render_cell_html($col_key, $produit, $cell_ctx); ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="page-produits-export-save-bar page-produits-export-save-bar--bottom">
                    <button type="submit" class="btn-primary page-produits-export-save-bar__btn">
                        <i class="fas fa-save" aria-hidden="true"></i> Enregistrer les prix
                    </button>
                </div>
                </form>

                <?php if ($total_pages > 1): ?>
                <nav class="page-produits-pagination page-produits-export-pagination" id="page-produits-export-pagination"
                    aria-label="Pagination de l’aperçu export">
                    <?php if ($page > 1): ?>
                    <?php $prev_q = array_merge($pagination_query_base, ['page' => $page - 1]); ?>
                    <a href="export-catalogue.php?<?php echo htmlspecialchars(http_build_query($prev_q), ENT_QUOTES, 'UTF-8'); ?>"
                        class="page-produits-pagination__link">
                        <i class="fas fa-chevron-left" aria-hidden="true"></i> Précédent
                    </a>
                    <?php endif; ?>

                    <span class="page-produits-pagination__info">
                        Page <?php echo (int) $page; ?> / <?php echo (int) $total_pages; ?>
                        <span class="page-produits-pagination__detail">(<?php echo (int) $export_preview_per_page; ?> par
                            page · <?php echo (int) $total_export; ?> au total · le PDF inclut tous les
                            résultats)</span>
                    </span>

                    <?php if ($page < $total_pages): ?>
                    <?php $next_q = array_merge($pagination_query_base, ['page' => $page + 1]); ?>
                    <a href="export-catalogue.php?<?php echo htmlspecialchars(http_build_query($next_q), ENT_QUOTES, 'UTF-8'); ?>"
                        class="page-produits-pagination__link">
                        Suivant <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    </a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="delete-confirm-overlay" id="deleteConfirmOverlay"></div>
    <div class="delete-confirm-modal" id="deleteConfirmModal" role="dialog" aria-modal="true"
        aria-labelledby="deleteConfirmTitle">
        <div class="delete-confirm-modal__icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="delete-confirm-modal__title" id="deleteConfirmTitle">Confirmer la suppression</h3>
        <p class="delete-confirm-modal__text">Êtes-vous sûr de vouloir supprimer ce produit ?</p>
        <div class="delete-confirm-modal__product" id="deleteConfirmProduct"></div>
        <p class="delete-confirm-modal__warning">
            <i class="fas fa-info-circle"></i> Cette action est irréversible
        </p>
        <div class="delete-confirm-modal__actions">
            <button type="button" class="delete-confirm-modal__btn delete-confirm-modal__btn--cancel"
                id="deleteConfirmCancel">
                <i class="fas fa-times"></i> Annuler
            </button>
            <button type="button" class="delete-confirm-modal__btn delete-confirm-modal__btn--confirm"
                id="deleteConfirmConfirm">
                <i class="fas fa-trash"></i> Confirmer
            </button>
        </div>
    </div>

    <div class="export-catalogue-table-modal-overlay" id="exportCatalogueTableModalOverlay" hidden></div>
    <div class="export-catalogue-table-modal" id="exportCatalogueTableModal" role="dialog" aria-modal="true"
        aria-labelledby="exportCatalogueTableModalTitle" hidden>
        <div class="export-catalogue-pdf-modal__header">
            <h2 class="export-catalogue-pdf-modal__title" id="exportCatalogueTableModalTitle">
                <i class="fas fa-table-columns" aria-hidden="true"></i> Colonnes du tableau
            </h2>
            <button type="button" class="export-catalogue-pdf-modal__close" id="exportCatalogueTableModalClose"
                aria-label="Fermer">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <p class="export-catalogue-pdf-modal__intro">
            Choisissez les données affichées dans le suivi catalogue. La liste disponible est synchronisée avec les
            <strong>champs formulaire produit</strong> actifs et autorisés pour votre profil.
        </p>
        <div class="export-catalogue-pdf-modal__toolbar">
            <button type="button" class="btn-secondary" id="exportCatalogueTableSelectAll">Tout cocher</button>
            <button type="button" class="btn-secondary" id="exportCatalogueTableSelectNone">Tout décocher</button>
        </div>
        <fieldset class="export-catalogue-pdf-modal__columns" id="exportCatalogueTableColumnsFieldset">
            <legend class="visually-hidden">Colonnes du tableau suivi</legend>
            <?php foreach ($suivi_columns_catalog as $col_key => $col_label): ?>
            <?php $locked = !empty($suivi_columns_defs[$col_key]['locked']); ?>
            <label class="export-catalogue-pdf-modal__column">
                <input type="checkbox" name="suivi_cols[]" value="<?php echo htmlspecialchars($col_key, ENT_QUOTES, 'UTF-8'); ?>"
                    data-suivi-table-col="<?php echo htmlspecialchars($col_key, ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo isset($suivi_visible_lookup[$col_key]) ? 'checked' : ''; ?>
                    <?php echo $locked ? 'disabled checked data-suivi-col-locked="1"' : ''; ?>>
                <span><?php echo htmlspecialchars($col_label, ENT_QUOTES, 'UTF-8'); ?><?php echo $locked ? ' (obligatoire)' : ''; ?></span>
            </label>
            <?php endforeach; ?>
        </fieldset>
        <p class="export-catalogue-pdf-modal__error" id="exportCatalogueTableModalError" hidden role="alert">
            Sélectionnez au moins une colonne.
        </p>
        <div class="export-catalogue-pdf-modal__actions">
            <button type="button" class="btn-secondary" id="exportCatalogueTableModalCancel">Annuler</button>
            <button type="button" class="btn-primary" id="exportCatalogueTableModalConfirm">
                <i class="fas fa-save" aria-hidden="true"></i> Enregistrer
            </button>
        </div>
    </div>

    <div class="export-catalogue-pdf-modal-overlay" id="exportCataloguePdfModalOverlay" hidden></div>
    <div class="export-catalogue-pdf-modal" id="exportCataloguePdfModal" role="dialog" aria-modal="true"
        aria-labelledby="exportCataloguePdfModalTitle" hidden>
        <div class="export-catalogue-pdf-modal__header">
            <h2 class="export-catalogue-pdf-modal__title" id="exportCataloguePdfModalTitle">
                <i class="fas fa-file-pdf" aria-hidden="true"></i> Contenu du PDF
            </h2>
            <button type="button" class="export-catalogue-pdf-modal__close" id="exportCataloguePdfModalClose"
                aria-label="Fermer">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <p class="export-catalogue-pdf-modal__intro">
            Choisissez les colonnes à inclure dans le catalogue PDF. Les filtres actuels seront conservés.
            La liste est synchronisée avec les <strong>champs formulaire produit</strong> actifs et autorisés pour votre profil.
        </p>
        <div class="export-catalogue-pdf-modal__toolbar">
            <button type="button" class="btn-secondary export-catalogue-pdf-modal__select-all" id="exportCataloguePdfSelectAll">
                Tout cocher
            </button>
            <button type="button" class="btn-secondary export-catalogue-pdf-modal__select-none" id="exportCataloguePdfSelectNone">
                Tout décocher
            </button>
        </div>
        <fieldset class="export-catalogue-pdf-modal__columns">
            <legend class="visually-hidden">Colonnes du PDF</legend>
            <?php foreach ($pdf_columns_catalog as $col_key => $col_label): ?>
            <?php $pdf_locked = !empty($pdf_columns_defs[$col_key]['locked']); ?>
            <label class="export-catalogue-pdf-modal__column">
                <input type="checkbox" name="pdf_cols[]" value="<?php echo htmlspecialchars($col_key, ENT_QUOTES, 'UTF-8'); ?>"
                    checked data-export-pdf-col="<?php echo htmlspecialchars($col_key, ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo $pdf_locked ? 'disabled checked data-export-pdf-col-locked="1"' : ''; ?>>
                <span><?php echo htmlspecialchars($col_label, ENT_QUOTES, 'UTF-8'); ?><?php echo $pdf_locked ? ' (obligatoire)' : ''; ?></span>
            </label>
            <?php endforeach; ?>
        </fieldset>
        <p class="export-catalogue-pdf-modal__error" id="exportCataloguePdfModalError" hidden role="alert">
            Sélectionnez au moins une colonne.
        </p>
        <div class="export-catalogue-pdf-modal__actions">
            <button type="button" class="btn-secondary" id="exportCataloguePdfModalCancel">Annuler</button>
            <button type="button" class="btn-primary" id="exportCataloguePdfModalConfirm">
                <i class="fas fa-download" aria-hidden="true"></i> Générer le PDF
            </button>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <script src="/js/admin-export-catalogue-suivi.js<?php echo asset_version_query(); ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var deleteOverlay = document.getElementById('deleteConfirmOverlay');
        var deleteModal = document.getElementById('deleteConfirmModal');
        var deleteProduct = document.getElementById('deleteConfirmProduct');
        var deleteCancel = document.getElementById('deleteConfirmCancel');
        var deleteConfirm = document.getElementById('deleteConfirmConfirm');
        var currentDeleteLink = null;

        function positionModal() {
            deleteModal.style.removeProperty('left');
            deleteModal.style.removeProperty('top');
        }

        function showModal(link) {
            currentDeleteLink = link;
            deleteProduct.textContent = link.getAttribute('data-delete-name') || 'ce produit';
            deleteOverlay.classList.add('visible');
            deleteModal.classList.add('visible', 'animated');
            deleteCancel.focus();
        }

        function hideModal() {
            deleteOverlay.classList.remove('visible');
            deleteModal.classList.remove('visible', 'animated');
            currentDeleteLink = null;
        }

        document.addEventListener('click', function(event) {
            var link = event.target.closest('.page-produits-section a[data-delete-confirm="true"]');
            if (!link) {
                return;
            }
            event.preventDefault();
            positionModal(link);
            showModal(link);
        });

        deleteCancel.addEventListener('click', hideModal);
        deleteOverlay.addEventListener('click', hideModal);
        deleteConfirm.addEventListener('click', function() {
            if (currentDeleteLink) {
                window.location.href = currentDeleteLink.href;
            }
        });
    });
    </script>

    <?php include '../includes/footer.php'; ?>