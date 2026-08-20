<?php
/**
 * Gestion du stock - Catégories et produits
 * Contenu déplacé depuis categories/index.php
 * Utilise la table produits et la colonne stock (plus de table stock_articles)
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_sous_categories.php';
require_once __DIR__ . '/../../includes/site_url.php';
require_once __DIR__ . '/../../includes/fpl_ui.php';

$categories = get_all_categories_with_count();
$nb_cat = count($categories);
$stock_sous_cat_ok = produits_has_column('sous_categorie_id')
    && function_exists('sous_categories_table_ok')
    && sous_categories_table_ok();
$sous_categories = $stock_sous_cat_ok ? get_all_sous_categories_with_categorie_nom() : [];
$sous_cat_count_by_categorie = [];
foreach ($sous_categories as $sc_row) {
    $cid_sc = (int) ($sc_row['categorie_id'] ?? 0);
    if ($cid_sc > 0) {
        $sous_cat_count_by_categorie[$cid_sc] = ($sous_cat_count_by_categorie[$cid_sc] ?? 0) + 1;
    }
}
$stock_upload_base = rtrim(get_public_root_uri_path(), '/') . '/upload/';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du stock — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
    <style>
        /* Page stock — cohérent avec variables.css (importé via admin-dashboard) */
        .stock-page {
            --stock-radius: 18px;
            --stock-radius-sm: 12px;
        }

        .stock-shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 1rem 1.25rem 3rem;
        }

        .stock-hero {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1.25rem 1.5rem;
            padding: 1.6rem 1.75rem;
            margin-bottom: 1.35rem;
            background: linear-gradient(135deg, var(--fond-principal) 0%, var(--bleu-pale) 55%, var(--fond-secondaire) 100%);
            border: 1px solid var(--border-input);
            border-radius: var(--stock-radius);
            box-shadow: var(--ombre-douce);
            border-left: 5px solid var(--couleur-dominante);
            position: relative;
            overflow: hidden;
        }

        .stock-hero::after {
            content: "";
            position: absolute;
            top: -40%;
            right: -15%;
            width: 45%;
            height: 140%;
            background: radial-gradient(ellipse, var(--orange-pale) 0%, transparent 70%);
            pointer-events: none;
        }

        .stock-hero__title-wrap {
            position: relative;
            z-index: 1;
        }

        .stock-hero h1 {
            margin: 0 0 0.35rem;
            font-family: var(--font-titres);
            font-size: clamp(1.45rem, 2.5vw, 1.85rem);
            font-weight: 700;
            color: var(--titres);
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .stock-hero h1 i {
            color: var(--couleur-dominante);
            font-size: 1.1em;
        }

        .stock-hero__badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.85rem;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 600;
            background: var(--fond-principal);
            color: var(--couleur-dominante);
            border: 1px solid var(--border-input);
            box-shadow: 0 1px 4px rgba(53, 100, 166, 0.08);
        }

        .stock-hero__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            position: relative;
            z-index: 1;
        }

        .stock-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.72rem 1.15rem;
            border-radius: var(--stock-radius-sm);
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
            border: 2px solid transparent;
        }

        .stock-btn--ghost {
            background: var(--fond-principal);
            color: var(--couleur-dominante);
            border-color: var(--border-input);
            box-shadow: 0 2px 8px rgba(53, 100, 166, 0.08);
        }

        .stock-btn--ghost:hover {
            border-color: var(--couleur-dominante);
            box-shadow: var(--ombre-douce);
            transform: translateY(-2px);
        }

        .stock-btn--accent {
            background: linear-gradient(135deg, var(--couleur-dominante) 0%, var(--bleu-fonce) 100%);
            color: var(--texte-clair);
            box-shadow: var(--ombre-promo);
        }

        .stock-btn--accent:hover {
            background: linear-gradient(135deg, var(--couleur-dominante-hover) 0%, var(--bleu-fonce) 100%);
            transform: translateY(-2px);
        }

        .stock-banner-ok {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.9rem 1.1rem;
            margin-bottom: 1.25rem;
            border-radius: var(--stock-radius-sm);
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--titres);
            font-weight: 500;
        }

        .stock-banner-ok i {
            color: var(--couleur-dominante);
        }

        .stock-section {
            background: var(--fond-principal);
            border: 1px solid var(--glass-border);
            border-radius: var(--stock-radius);
            padding: 1.35rem 1.35rem 1.6rem;
            box-shadow: var(--glass-shadow);
        }

        .stock-section__head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 1.35rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-input);
        }

        .stock-section__head h2 {
            margin: 0;
            font-family: var(--font-titres);
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--titres);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stock-section__head h2 i {
            color: var(--accent-promo);
        }

        .stock-cat-grid {
            display: grid;
            gap: clamp(0.45rem, 2.2vw, 0.95rem);
            grid-template-columns: repeat(auto-fill, minmax(0, 250px));
            justify-content: start;
        }

        .stock-cat-card {
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 250px;
            background: var(--fond-principal);
            border-radius: calc(var(--stock-radius-sm) - 2px);
            overflow: hidden;
            box-shadow: 0 1px 10px rgba(53, 100, 166, 0.06);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            border: 1px solid var(--border-input);
        }

        .stock-cat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--ombre-douce);
            border-color: rgba(53, 100, 166, 0.28);
        }

        .stock-cat-card__media {
            position: relative;
            aspect-ratio: 5 / 3;
            max-height: 120px;
            background: linear-gradient(180deg, var(--fond-secondaire) 0%, var(--blanc-neige) 100%);
            overflow: hidden;
        }

        .stock-cat-card__media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .stock-cat-card__placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--couleur-dominante);
            font-size: 1.5rem;
            opacity: 0.55;
        }

        .stock-cat-card__body {
            padding: 0.65rem 0.75rem 0.75rem;
            display: flex;
            flex-direction: column;
            flex: 1;
            gap: 0.35rem;
        }

        .stock-cat-card__body h3 {
            margin: 0;
            font-size: clamp(0.82rem, 2.8vw, 0.92rem);
            font-weight: 700;
            color: var(--titres);
            line-height: 1.25;
        }

        .stock-cat-card__desc {
            margin: 0;
            font-size: clamp(0.68rem, 2.2vw, 0.76rem);
            line-height: 1.35;
            color: var(--texte-mute);
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .stock-cat-card__actions {
            display: flex;
            flex-wrap: wrap;
            gap: clamp(0.25rem, 1.5vw, 0.35rem);
            margin-top: 0.15rem;
        }

        .stock-cat-card__actions a {
            flex: 1 1 calc(50% - 0.2rem);
            min-width: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            padding: clamp(0.32rem, 1.8vw, 0.42rem) clamp(0.3rem, 1.5vw, 0.45rem);
            border-radius: 8px;
            font-size: clamp(0.62rem, 2.4vw, 0.7rem);
            font-weight: 600;
            text-decoration: none;
            transition: background 0.15s, color 0.15s, transform 0.15s;
        }

        .stock-cat-card__actions a i {
            font-size: 0.85em;
        }

        /* Supprimer : pleine largeur sur la 3e ligne */
        .stock-cat-card__actions .stock-act-del {
            flex: 1 1 100%;
        }

        .stock-act-view {
            background: var(--bleu-pale);
            color: var(--couleur-dominante);
            border: 1px solid var(--border-input);
        }

        .stock-act-view:hover {
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            transform: translateY(-1px);
        }

        .stock-act-edit {
            background: var(--fond-secondaire);
            color: var(--gris-fonce);
            border: 1px solid var(--border-input);
        }

        .stock-act-edit:hover {
            border-color: var(--couleur-dominante);
            color: var(--couleur-dominante);
        }

        .stock-act-del {
            background: var(--error-bg);
            color: var(--orange-fonce);
            border: 1px solid var(--error-border);
        }

        .stock-act-del:hover {
            background: var(--orange);
            color: var(--texte-clair);
            border-color: var(--orange);
        }

        /* Mobile : hero compact, actions en colonne pleine largeur */
        @media (max-width: 768px) {
            .stock-hero {
                min-height: 0;
                align-items: stretch;
                padding: 1.1rem 1.15rem;
                gap: 0.85rem;
                margin-bottom: 1rem;
            }

            .stock-hero__title-wrap {
                width: 100%;
            }

            .stock-hero h1 {
                font-size: clamp(1.05rem, 3.8vw, 1.45rem);
                gap: 0.5rem;
                margin: 0 0 0.28rem;
            }

            .stock-hero h1 i {
                font-size: 1em;
            }

            .stock-hero__badge {
                font-size: 0.75rem;
                padding: 0.28rem 0.65rem;
            }

            .stock-hero__actions {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }

            .stock-btn {
                width: 100%;
                justify-content: center;
                padding: 0.62rem 0.9rem;
                font-size: 0.82rem;
            }
        }

        /* Mobile / petit écran : 2 cartes par ligne, largeur fluide */
        @media (max-width: 720px) {
            .stock-cat-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .stock-cat-card {
                max-width: none;
            }
        }

        .stock-empty {
            text-align: center;
            padding: 2.75rem 1.5rem;
            background: var(--fond-secondaire);
            border-radius: var(--stock-radius-sm);
            border: 1px dashed var(--border-input);
        }

        .stock-empty i {
            font-size: 2.5rem;
            color: var(--couleur-dominante);
            opacity: 0.45;
            margin-bottom: 1rem;
        }

        .stock-empty h3 {
            margin: 0 0 0.5rem;
            font-family: var(--font-titres);
            color: var(--titres);
        }

        .stock-empty p {
            margin: 0 0 1.25rem;
            color: var(--texte-mute);
            font-size: 0.95rem;
        }

        @media (max-width: 640px) {
            .stock-hero {
                padding: 0.85rem 1rem;
                gap: 0.65rem;
                border-radius: 14px;
            }

            .stock-hero h1 {
                font-size: clamp(1rem, 4.2vw, 1.2rem);
            }

            .stock-hero__badge {
                font-size: 0.72rem;
            }

            .stock-btn {
                padding: 0.55rem 0.8rem;
                font-size: 0.78rem;
            }
        }

        @media (max-width: 400px) {
            .stock-hero {
                padding: 0.75rem 0.85rem;
            }

            .stock-hero h1 {
                font-size: 0.95rem;
                flex-wrap: wrap;
            }
        }

        /* Bandeau sous-catégories (horizontal) */
        .stock-sous-categories {
            margin: 0 0 1.25rem;
            padding: 1rem 1.1rem;
            background: var(--fond-principal);
            border: 1px solid var(--border-input);
            border-radius: var(--stock-radius-sm);
        }

        .stock-sous-categories__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.85rem;
        }

        .stock-sous-categories__title {
            margin: 0;
            font-size: 1rem;
            font-weight: 650;
            color: var(--titres);
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }

        .stock-sous-categories__title i {
            color: var(--couleur-dominante);
        }

        .stock-sous-categories__all {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--couleur-dominante);
            text-decoration: none;
            padding: 0.35rem 0.65rem;
            border-radius: 8px;
        }

        .stock-sous-categories__all:hover {
            background: var(--bleu-pale);
        }

        .stock-sous-categories__track {
            display: flex;
            gap: 0.75rem;
            overflow-x: auto;
            padding: 0.2rem 0.1rem 0.5rem;
            scroll-snap-type: x proximity;
            -webkit-overflow-scrolling: touch;
        }

        .stock-sous-cat-card {
            flex: 0 0 auto;
            width: 118px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.35rem;
            padding: 0.65rem 0.5rem;
            border-radius: 12px;
            border: 1px solid var(--border-input);
            background: var(--fond-secondaire);
            text-decoration: none;
            color: var(--titres);
            scroll-snap-align: start;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
        }

        .stock-sous-cat-card:hover {
            border-color: var(--couleur-dominante);
            box-shadow: var(--ombre-douce);
            transform: translateY(-2px);
        }

        .stock-sous-cat-card.is-active {
            border-color: var(--couleur-dominante);
            background: var(--bleu-pale);
            box-shadow: 0 0 0 2px rgba(16, 49, 111, 0.1);
        }

        .stock-sous-cat-card__img {
            width: 52px;
            height: 52px;
            border-radius: 10px;
            background: var(--fond-principal);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--couleur-dominante);
            font-size: 1.2rem;
        }

        .stock-sous-cat-card__name {
            font-size: 0.72rem;
            font-weight: 650;
            text-align: center;
            line-height: 1.25;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .stock-sous-cat-card__meta {
            font-size: 0.62rem;
            color: var(--texte-mute);
            text-align: center;
            line-height: 1.2;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Tableau catégories */
        .stock-cat-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border-input);
            border-radius: var(--stock-radius-sm);
            background: var(--fond-principal);
        }

        .stock-cat-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .stock-cat-table thead th {
            text-align: left;
            padding: 0.75rem 1rem;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--texte-mute);
            background: var(--fond-secondaire);
            border-bottom: 1px solid var(--border-input);
            white-space: nowrap;
        }

        .stock-cat-table tbody td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-input);
            color: var(--titres);
            vertical-align: middle;
        }

        .stock-cat-table tbody tr:nth-child(even) td {
            background: #fafbfd;
        }

        .stock-cat-table tbody tr:hover td {
            background: var(--bleu-pale);
        }

        .stock-cat-table__row--linkable {
            cursor: pointer;
        }

        .stock-cat-table .col-num {
            font-variant-numeric: tabular-nums;
            font-weight: 600;
            white-space: nowrap;
        }

        .stock-cat-table .col-thumb {
            width: 56px;
        }

        .stock-cat-table .col-actions {
            width: 110px;
            white-space: nowrap;
        }

        .stock-cat-table__thumb {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            object-fit: cover;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--fond-secondaire);
            border: 1px solid var(--border-input);
        }

        .stock-cat-table__thumb--ph {
            color: var(--couleur-dominante);
            font-size: 1rem;
        }

        .stock-cat-table__nom {
            font-weight: 650;
            color: var(--couleur-dominante);
        }

        .stock-cat-table__action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            color: var(--couleur-dominante);
            text-decoration: none;
            transition: background 0.15s ease;
        }

        .stock-cat-table__action:hover {
            background: var(--bleu-pale);
        }

        .stock-cat-table__action--danger {
            color: #c0392b;
        }

        .stock-cat-table__action--danger:hover {
            background: rgba(192, 57, 43, 0.1);
        }

        @media (max-width: 768px) {
            .stock-cat-table thead {
                display: none;
            }

            .stock-cat-table tbody tr {
                display: block;
                border-bottom: 1px solid var(--border-input);
                padding: 0.75rem 0.85rem;
            }

            .stock-cat-table tbody td {
                display: flex;
                justify-content: space-between;
                gap: 0.75rem;
                padding: 0.45rem 0;
                border-bottom: none;
            }

            .stock-cat-table tbody td::before {
                content: attr(data-label);
                font-weight: 650;
                font-size: 11px;
                color: var(--texte-mute);
            }

            .stock-cat-table .col-thumb::before {
                display: none;
            }
        }
    </style>
</head>

<body class="stock-page">
    <?php include '../includes/nav.php'; ?>

    <div class="stock-shell">
        <header class="stock-hero">
            <div class="stock-hero__title-wrap">
                <h1><i class="fas fa-boxes-stacked" aria-hidden="true"></i> Gestion du stock</h1>
                <span class="stock-hero__badge" aria-live="polite">
                    <i class="fas fa-tags" aria-hidden="true"></i>
                    <?php echo (int) $nb_cat; ?> catégorie<?php echo $nb_cat > 1 ? 's' : ''; ?>
                </span>
            </div>
            <div class="stock-hero__actions">
                <a href="mouvements.php" class="stock-btn stock-btn--ghost">
                    <i class="fas fa-history" aria-hidden="true"></i> Historique des mouvements
                </a>
                <?php if ($stock_sous_cat_ok && !admin_is_restricted_admin_account()): ?>
                <a href="sous-categories/index.php" class="stock-btn stock-btn--ghost">
                    <i class="fas fa-sitemap" aria-hidden="true"></i> Voir les sous-catégories
                </a>
                <?php endif; ?>
                <?php if (!admin_is_restricted_admin_account()): ?>
                <a href="../categories/ajouter.php" class="stock-btn stock-btn--accent">
                    <i class="fas fa-plus" aria-hidden="true"></i> Nouvelle catégorie
                </a>
                <?php endif; ?>
            </div>
        </header>

        <?php if (!empty($_SESSION['produit_form_notice'])): ?>
        <div class="stock-banner-ok" role="status" style="border-color: var(--border-input); background: var(--bleu-pale);">
            <i class="fas fa-info-circle" aria-hidden="true"></i>
            <span><?php echo htmlspecialchars((string) $_SESSION['produit_form_notice'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php unset($_SESSION['produit_form_notice']); endif; ?>

        <?php
        if (!empty($success_message)) {
            $flash_success_message = $success_message;
            include __DIR__ . '/../includes/flash_success_popup.php';
            $success_message = '';
        }
        ?>

        <section class="stock-section" aria-labelledby="stock-cat-heading">
            <div class="stock-section__head">
                <h2 id="stock-cat-heading"><i class="fas fa-layer-group" aria-hidden="true"></i> Catalogue par catégorie</h2>
            </div>

            <?php if ($stock_sous_cat_ok && !empty($sous_categories)): ?>
                <?php include __DIR__ . '/includes/sous_categories_carousel.php'; ?>
            <?php endif; ?>

            <?php if (empty($categories)): ?>
            <div class="stock-empty">
                <i class="fas fa-tags" aria-hidden="true"></i>
                <h3>Aucune catégorie</h3>
                <p><?php echo admin_is_restricted_admin_account()
                    ? 'Aucune catégorie n’est encore définie.'
                    : 'Créez une première catégorie pour organiser vos produits et le stock.'; ?></p>
                <?php if (!admin_is_restricted_admin_account()): ?>
                <a href="../categories/ajouter.php" class="stock-btn stock-btn--accent">
                    <i class="fas fa-plus" aria-hidden="true"></i> Ajouter une catégorie
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="stock-cat-table-wrap">
                <table class="stock-cat-table">
                    <thead>
                        <tr>
                            <th class="col-thumb">Visuel</th>
                            <th>Catégorie</th>
                            <th class="col-num">Produits</th>
                            <?php if ($stock_sous_cat_ok): ?>
                            <th class="col-num">Sous-cat.</th>
                            <?php endif; ?>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $upload_base = $stock_upload_base;
                        foreach ($categories as $categorie):
                            $nb_sous_cat = $sous_cat_count_by_categorie[(int) ($categorie['id'] ?? 0)] ?? 0;
                            include __DIR__ . '/includes/ligne_categorie_table.php';
                        endforeach;
                        ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <?php include __DIR__ . '/../../includes/admin_stock_alerte_popup.php'; ?>

    <?php include '../includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', function (event) {
                var row = event.target.closest('.stock-cat-table__row--linkable');
                if (!row) {
                    return;
                }
                if (event.target.closest('.stock-cat-table__action')) {
                    return;
                }
                var href = row.getAttribute('data-href');
                if (href) {
                    window.location.href = href;
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                var row = event.target.closest('.stock-cat-table__row--linkable');
                if (!row || event.target.closest('.stock-cat-table__action')) {
                    return;
                }
                event.preventDefault();
                var href = row.getAttribute('data-href');
                if (href) {
                    window.location.href = href;
                }
            });
        });
    </script>
</body>

</html>
