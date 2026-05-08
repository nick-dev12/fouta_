<?php
/**
 * Page de liste des produits
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

// Afficher le message de succès s'il existe
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Récupérer tous les produits
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_categories.php';
$produits = get_all_produits();
$categories = get_all_categories();
$recherche = trim($_GET['recherche'] ?? '');
$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;

if (!empty($produits)) {
    $produits = array_values(array_filter($produits, function ($produit) use ($recherche, $categorie_id) {
        if ($categorie_id > 0 && (int) ($produit['categorie_id'] ?? 0) !== $categorie_id) {
            return false;
        }

        if ($recherche === '') {
            return true;
        }

        // Code interne FPLxxxxxx (exact, insensible à la casse)
        if (preg_match('/^FPL(\d{6}|\d{9})$/i', $recherche)) {
            $code = strtoupper($recherche);
            $ident = strtoupper(trim((string) ($produit['identifiant_interne'] ?? '')));
            return $ident !== '' && $ident === $code;
        }

        // 5 derniers chiffres du numéro (saisie rapide, type caisse supermarché)
        if (preg_match('/^\d{5}$/', $recherche)) {
            $ident = $produit['identifiant_interne'] ?? '';

            return produit_identifiant_derniers_5_chiffres($ident) === $recherche;
        }

        $needle = function_exists('mb_strtolower') ? mb_strtolower($recherche) : strtolower($recherche);
        $haystacks = [
            $produit['nom'] ?? '',
            $produit['description'] ?? '',
            $produit['categorie_nom'] ?? '',
            $produit['statut'] ?? '',
            (string) ($produit['identifiant_interne'] ?? ''),
        ];

        foreach ($haystacks as $value) {
            $value = function_exists('mb_strtolower') ? mb_strtolower((string) $value) : strtolower((string) $value);
            if (strpos($value, $needle) !== false) {
                return true;
            }
        }

        return false;
    }));
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Produits - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-produits-index.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="page-produits-admin">
        <div class="content-header dashboard-hero page-produits-hero">
            <div class="dashboard-hero-text">
                <p class="dashboard-eyebrow">Catalogue boutique</p>
                <h1 id="page-produits-title"><i class="fas fa-box" aria-hidden="true"></i> Liste des produits</h1>
                <p class="dashboard-subtitle">Gérez le catalogue, les stocks et les tarifs. Recherchez par nom, code <strong>FPL</strong> ou les <strong>5 derniers chiffres</strong> du numéro (caisse).</p>
                <div class="page-produits-hero__actions">
                    <a href="ajouter.php" class="btn-primary page-produits-hero__btn">
                        <i class="fas fa-upload" aria-hidden="true"></i> Publier un produit
                    </a>
                </div>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success page-produits-flash" role="status">
                <i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

    <section class="produits-section page-produits-section" aria-labelledby="produits-section-heading">
        <div class="section-title page-produits-section__head">
            <h2 id="produits-section-heading"><i class="fas fa-th-large" aria-hidden="true"></i> Tous les produits <span class="page-produits-count">(<?php echo count($produits); ?>)</span></h2>
        </div>

        <form method="GET" action="" class="admin-filters-bar page-produits-filters">
            <div class="admin-filter-field">
                <label for="recherche">Recherche</label>
                <input type="text" id="recherche" name="recherche"
                    placeholder="Nom, FPL000151 ou 5 chiffres (ex. 00151)…"
                    value="<?php echo htmlspecialchars($recherche); ?>" autocomplete="off" inputmode="search">
            </div>
            <div class="admin-filter-field">
                <label for="categorie_id">Catégorie</label>
                <select id="categorie_id" name="categorie_id">
                    <option value="0">Toutes les catégories</option>
                    <?php foreach ($categories as $categorie): ?>
                        <option value="<?php echo (int) $categorie['id']; ?>" <?php echo $categorie_id === (int) $categorie['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($categorie['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-filter-actions">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-search"></i> Filtrer
                </button>
                <a href="index.php" class="btn-filter-reset">
                    <i class="fas fa-rotate-left"></i>&nbsp;Réinitialiser
                </a>
            </div>
        </form>

        <?php if (empty($produits)): ?>
            <div class="empty-state page-produits-empty">
                <div class="page-produits-empty__icon" aria-hidden="true"><i class="fas fa-box-open"></i></div>
                <p class="page-produits-empty__title">Aucun produit à afficher</p>
                <p class="page-produits-empty__hint">Élargissez la recherche, choisissez « Toutes les catégories » ou <a href="index.php">réinitialisez les filtres</a>. Vous pouvez aussi ajouter un produit.</p>
                <a href="ajouter.php" class="btn-primary page-produits-empty__cta">
                    <i class="fas fa-upload" aria-hidden="true"></i> Publier un produit
                </a>
            </div>
        <?php else: ?>
            <ul class="produits-grid page-produits-grid" role="list">
                <?php foreach ($produits as $produit): ?>
                    <li class="produit-card produit-card--admin produit-card-linkable"
                        data-href="modifier.php?id=<?php echo (int) $produit['id']; ?>" role="listitem">
                        <?php
                        $statut_class = 'statut-actif';
                        if ($produit['statut'] == 'inactif') {
                            $statut_class = 'statut-inactif';
                        } elseif ($produit['statut'] == 'rupture_stock') {
                            $statut_class = 'statut-rupture';
                        }
                        $statut_label = ucfirst(str_replace('_', ' ', (string) ($produit['statut'] ?? '')));
                        ?>
                        <span class="statut-badge produit-card__statut <?php echo $statut_class; ?>"><?php echo htmlspecialchars($statut_label, ENT_QUOTES, 'UTF-8'); ?></span>
                        <div class="produit-card-media">
                            <?php
                            $img_principale = '';
                            if (!empty($produit['image_principale'])) {
                                $img_principale = trim((string) $produit['image_principale']);
                            }
                            if ($img_principale !== ''):
                            ?>
                            <img src="/upload/<?php echo htmlspecialchars($img_principale, ENT_QUOTES, 'UTF-8'); ?>"
                                alt="<?php echo htmlspecialchars((string) ($produit['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                class="produit-card-image"
                                onerror="this.onerror=null;var w=document.createElement('div');w.className='produit-card-media-placeholder';w.setAttribute('role','img');w.setAttribute('aria-label','Sans image');w.innerHTML='<i class=\'fas fa-truck\' aria-hidden=\'true\'></i>';this.replaceWith(w);"
                                width="300" height="300" loading="lazy" decoding="async">
                            <?php else: ?>
                            <div class="produit-card-media-placeholder" role="img" aria-label="Pas d'image">
                                <i class="fas fa-truck" aria-hidden="true"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="produit-card-body">
                            <h3 class="produit-card-nom"><?php echo htmlspecialchars((string) ($produit['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="produit-card-categorie">
                                <i class="fas fa-tag" aria-hidden="true"></i>
                                <?php echo htmlspecialchars((string) ($produit['categorie_nom'] ?? 'Sans catégorie'), ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                            <p class="produit-card-prix">
                                <?php echo number_format((float) ($produit['prix'] ?? 0), 0, ',', ' '); ?>
                                <span class="prix-unite">FCFA</span>
                                <?php if (!empty($produit['prix_promotion'])): ?>
                                    <span class="prix-promo">
                                        (Promo: <?php echo number_format((float) $produit['prix_promotion'], 0, ',', ' '); ?> FCFA)
                                    </span>
                                <?php endif; ?>
                            </p>
                            <p class="produit-card-stock">
                                <i class="fas fa-cubes" aria-hidden="true"></i>
                                <span class="produit-card-stock__label">Stock</span>
                                <span class="stock-value"><?php echo $produit['stock']; ?></span>
                            </p>
                            <div class="produit-card-actions produit-card-actions--admin">
                                <a href="ajuster-stock.php?id=<?php echo $produit['id']; ?>" class="btn-card btn-stock"
                                    title="Ajuster le stock">
                                    <i class="fas fa-boxes-stacked" aria-hidden="true"></i> Stock
                                </a>
                                <a href="modifier.php?id=<?php echo $produit['id']; ?>" class="btn-card btn-edit">
                                    <i class="fas fa-edit" aria-hidden="true"></i> Modifier
                                </a>
                                <a href="supprimer.php?id=<?php echo $produit['id']; ?>" class="btn-card btn-delete"
                                    onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?');">
                                    <i class="fas fa-trash" aria-hidden="true"></i> Supprimer
                                </a>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    </div><!-- .page-produits-admin -->

    <?php include '../includes/footer.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.produit-card-linkable').forEach(function (card) {
                card.addEventListener('click', function (event) {
                    if (event.target.closest('a, button, input, select, textarea, form')) {
                        return;
                    }
                    var href = card.getAttribute('data-href');
                    if (href) {
                        window.location.href = href;
                    }
                });
            });
        });
    </script>