<?php
/**
 * Bandeau horizontal des catégories — page liste des pièces
 *
 * Deux niveaux, comme dans FPL natif : tant qu'aucune catégorie n'est choisie,
 * on voit les catégories ; dès qu'on en ouvre une, le bandeau montre SES rayons
 * et un fil d'Ariane dit où l'on se trouve. Une carte « + » ferme la marche pour
 * créer sans quitter la page.
 *
 * Variables : $categories (array), $categorie_id (int), $upload_base (string),
 *             $sous_categories_bandeau (array), $sous_categorie_id (int),
 *             $categorie_courante_nom (string)
 */
if (!isset($categories) || !is_array($categories)) {
    return;
}

$categorie_id = isset($categorie_id) ? (int) $categorie_id : 0;
$sous_categorie_id = isset($sous_categorie_id) ? (int) $sous_categorie_id : 0;
$sous_categories_bandeau = isset($sous_categories_bandeau) && is_array($sous_categories_bandeau)
    ? $sous_categories_bandeau : [];
$categorie_courante_nom = isset($categorie_courante_nom) ? (string) $categorie_courante_nom : '';

$upload_base = isset($upload_base) ? (string) $upload_base : '/upload/';
if ($upload_base !== '' && substr($upload_base, -1) !== '/') {
    $upload_base .= '/';
}

if (!function_exists('e')) {
    require_once __DIR__ . '/../../../includes/fpl_ui.php';
}

// Au premier niveau on montre les catégories, au second les rayons.
$dans_une_categorie = ($categorie_id > 0);
$cartes = $dans_une_categorie ? $sous_categories_bandeau : $categories;
$peut_gerer = !function_exists('admin_is_restricted_admin_account')
    || !admin_is_restricted_admin_account();
?>
<section class="page-produits-categories" aria-label="Parcourir par catégorie">
    <div class="page-produits-categories__head">
        <h2 class="page-produits-categories__title">
            <i class="fas fa-layer-group" aria-hidden="true"></i>
            <?php echo $dans_une_categorie ? 'Rayons' : 'Catégories'; ?>
        </h2>

        <?php if ($dans_une_categorie): ?>
        <nav class="fpl-fil" aria-label="Fil d’Ariane">
            <a href="index.php">Pièces</a>
            <span class="fpl-fil__sep" aria-hidden="true">›</span>
            <?php if ($sous_categorie_id > 0): ?>
            <a href="index.php?categorie_id=<?php echo $categorie_id; ?>"><?php echo e($categorie_courante_nom); ?></a>
            <span class="fpl-fil__sep" aria-hidden="true">›</span>
            <strong><?php echo e($sous_categorie_courante_nom ?? ''); ?></strong>
            <?php else: ?>
            <strong><?php echo e($categorie_courante_nom); ?></strong>
            <?php endif; ?>
        </nav>
        <?php else: ?>
        <a href="index.php" class="page-produits-categories__all is-active">Toutes</a>
        <?php endif; ?>
    </div>

    <div class="page-produits-categories__track" tabindex="0">
        <?php if ($dans_une_categorie): ?>
        <?php // Remonter d'un niveau : la première carte, toujours au même endroit. ?>
        <a href="index.php" class="page-produits-cat-card fpl-cat-card--retour" title="Revenir aux catégories">
            <span class="page-produits-cat-card__img page-produits-cat-card__img--all" aria-hidden="true">
                <i class="fas fa-arrow-left"></i>
            </span>
            <span class="page-produits-cat-card__name">Retour</span>
        </a>
        <a href="index.php?categorie_id=<?php echo $categorie_id; ?>"
            class="page-produits-cat-card <?php echo $sous_categorie_id === 0 ? 'is-active' : ''; ?>"
            title="Toutes les pièces de <?php echo e($categorie_courante_nom); ?>">
            <span class="page-produits-cat-card__img page-produits-cat-card__img--all" aria-hidden="true">
                <i class="fas fa-th"></i>
            </span>
            <span class="page-produits-cat-card__name">Toutes</span>
        </a>
        <?php else: ?>
        <a href="index.php" class="page-produits-cat-card <?php echo $categorie_id === 0 ? 'is-active' : ''; ?>">
            <span class="page-produits-cat-card__img page-produits-cat-card__img--all" aria-hidden="true">
                <i class="fas fa-th"></i>
            </span>
            <span class="page-produits-cat-card__name">Toutes</span>
        </a>
        <?php endif; ?>

        <?php foreach ($cartes as $carte):
            $cid = (int) ($carte['id'] ?? 0);
            $img = trim((string) ($carte['image'] ?? ''));   // sous_categories n'a pas cette colonne : reste vide
            $nom = (string) ($carte['nom'] ?? '');
            if ($dans_une_categorie) {
                $lien = 'index.php?categorie_id=' . $categorie_id . '&amp;sous_categorie_id=' . $cid;
                $actif = ($sous_categorie_id === $cid);
            } else {
                $lien = 'index.php?categorie_id=' . $cid;
                $actif = ($categorie_id === $cid);
            }
            ?>
        <a href="<?php echo $lien; ?>"
            class="page-produits-cat-card <?php echo $actif ? 'is-active' : ''; ?>"
            title="<?php echo e($nom); ?>">
            <?php if ($img !== ''): ?>
            <span class="page-produits-cat-card__img">
                <img src="<?php echo e($upload_base . $img); ?>" alt="" loading="lazy" decoding="async"
                    onerror="this.parentElement.classList.add('page-produits-cat-card__img--ph');this.remove();">
            </span>
            <?php else: ?>
            <span class="page-produits-cat-card__img page-produits-cat-card__img--ph" aria-hidden="true">
                <i class="fas <?php echo $dans_une_categorie ? 'fa-boxes-stacked' : 'fa-tag'; ?>"></i>
            </span>
            <?php endif; ?>
            <span class="page-produits-cat-card__name"><?php echo e($nom); ?></span>
        </a>
        <?php endforeach; ?>

        <?php if ($peut_gerer): ?>
        <?php // La carte « + » ferme la marche : créer sans quitter la page. ?>
        <a href="<?php echo $dans_une_categorie
                ? '../stock/index.php'
                : '../categories/ajouter.php'; ?>"
            class="page-produits-cat-card fpl-cat-card--neuve"
            title="<?php echo $dans_une_categorie ? 'Créer un rayon' : 'Créer une catégorie'; ?>">
            <span class="page-produits-cat-card__img page-produits-cat-card__img--ph" aria-hidden="true">
                <i class="fas fa-plus"></i>
            </span>
            <span class="page-produits-cat-card__name"><?php echo $dans_une_categorie ? 'Nouveau rayon' : 'Nouvelle catégorie'; ?></span>
        </a>
        <?php endif; ?>
    </div>
</section>
