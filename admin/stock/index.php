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

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

require_once __DIR__ . '/../../models/model_categories.php';
$categories = get_all_categories();
$nb_cat = count($categories);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du stock — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
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
            grid-template-columns: repeat(auto-fill, minmax(288px, 1fr));
            gap: 1.15rem;
        }

        .stock-cat-card {
            display: flex;
            flex-direction: column;
            background: var(--fond-principal);
            border-radius: var(--stock-radius-sm);
            overflow: hidden;
            box-shadow: 0 2px 14px rgba(53, 100, 166, 0.06);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            border: 1px solid var(--border-input);
        }

        .stock-cat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--ombre-gourmande);
            border-color: rgba(53, 100, 166, 0.35);
        }

        .stock-cat-card__media {
            position: relative;
            aspect-ratio: 16 / 10;
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
            font-size: 2.25rem;
            opacity: 0.55;
        }

        .stock-cat-card__body {
            padding: 1.1rem 1.15rem 1.15rem;
            display: flex;
            flex-direction: column;
            flex: 1;
            gap: 0.65rem;
        }

        .stock-cat-card__body h3 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--titres);
            line-height: 1.3;
        }

        .stock-cat-card__desc {
            margin: 0;
            font-size: 0.86rem;
            line-height: 1.45;
            color: var(--texte-mute);
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .stock-cat-card__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-top: 0.25rem;
        }

        .stock-cat-card__actions a {
            flex: 1 1 auto;
            min-width: 6.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.5rem 0.65rem;
            border-radius: 10px;
            font-size: 0.78rem;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.15s, color 0.15s, transform 0.15s;
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
                padding: 1.25rem;
            }

            .stock-hero__actions {
                width: 100%;
            }

            .stock-btn {
                flex: 1 1 auto;
                justify-content: center;
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
                <a href="../categories/ajouter.php" class="stock-btn stock-btn--accent">
                    <i class="fas fa-plus" aria-hidden="true"></i> Nouvelle catégorie
                </a>
            </div>
        </header>

        <?php if (!empty($success_message)): ?>
        <div class="stock-banner-ok" role="status">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <span><?php echo htmlspecialchars($success_message); ?></span>
        </div>
        <?php endif; ?>

        <section class="stock-section" aria-labelledby="stock-cat-heading">
            <div class="stock-section__head">
                <h2 id="stock-cat-heading"><i class="fas fa-layer-group" aria-hidden="true"></i> Catalogue par catégorie</h2>
            </div>

            <?php if (empty($categories)): ?>
            <div class="stock-empty">
                <i class="fas fa-tags" aria-hidden="true"></i>
                <h3>Aucune catégorie</h3>
                <p>Créez une première catégorie pour organiser vos produits et le stock.</p>
                <a href="../categories/ajouter.php" class="stock-btn stock-btn--accent">
                    <i class="fas fa-plus" aria-hidden="true"></i> Ajouter une catégorie
                </a>
            </div>
            <?php else: ?>
            <div class="stock-cat-grid">
                <?php foreach ($categories as $categorie): ?>
                <article class="stock-cat-card">
                    <div class="stock-cat-card__media">
                        <?php if (!empty($categorie['image'])): ?>
                        <img src="/upload/<?php echo htmlspecialchars($categorie['image']); ?>"
                            alt=""
                            onerror="this.onerror=null;this.src='/image/produit1.jpg'">
                        <?php else: ?>
                        <div class="stock-cat-card__placeholder" aria-hidden="true">
                            <i class="fas fa-tag"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="stock-cat-card__body">
                        <h3><?php echo htmlspecialchars($categorie['nom']); ?></h3>
                        <p class="stock-cat-card__desc">
                            <?php echo htmlspecialchars($categorie['description'] ?? 'Aucune description'); ?>
                        </p>
                        <div class="stock-cat-card__actions">
                            <a href="../categories/produits.php?id=<?php echo (int) $categorie['id']; ?>"
                                class="stock-act-view">
                                <i class="fas fa-box" aria-hidden="true"></i> Produits
                            </a>
                            <a href="../categories/modifier.php?id=<?php echo (int) $categorie['id']; ?>"
                                class="stock-act-edit">
                                <i class="fas fa-edit" aria-hidden="true"></i> Modifier
                            </a>
                            <a href="../categories/supprimer.php?id=<?php echo (int) $categorie['id']; ?>"
                                class="stock-act-del"
                                onclick="return confirm('Supprimer cette catégorie ?');">
                                <i class="fas fa-trash" aria-hidden="true"></i> Supprimer
                            </a>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>

</html>
