<?php
/**
 * Bandeau horizontal des catégories — page liste des pièces
 *
 * Deux niveaux, comme dans FPL natif : tant qu'aucune catégorie n'est choisie,
 * on voit les catégories ; dès qu'on en ouvre une, le bandeau montre SES
 * sous-catégories, et un fil d'Ariane dit où l'on se trouve. Une carte « + » ferme la marche pour
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
// Répare à l'affichage les noms doublement encodés (2 catégories du siège).
require_once __DIR__ . '/../../../includes/fpl_texte.php';

// Au premier niveau les catégories, au second les sous-catégories.
$dans_une_categorie = ($categorie_id > 0);
$cartes = $dans_une_categorie ? $sous_categories_bandeau : $categories;
$peut_gerer = !function_exists('admin_is_restricted_admin_account')
    || !admin_is_restricted_admin_account();
?>
<section class="page-produits-categories" aria-label="Parcourir par catégorie">
    <?php // Le fil d'Ariane a REMONTÉ dans l'en-tête de la page, à la place du
          // titre, comme dans FPL natif. Il n'est plus ici : le répéter deux
          // fois sur le même écran n'apprenait rien. ?>

    <?php // Les deux flèches encadrent le bandeau, comme dans FPL natif :
          // celle de gauche avant les cartes, celle de droite après. ?>
    <div class="fpl-rail-wrap">
    <button type="button" class="fpl-rail-arrow" data-fpl-rail-dir="-1" title="Précédent" aria-label="Faire défiler vers la gauche">
        <i class="fas fa-chevron-left" aria-hidden="true"></i>
    </button>
    <div class="page-produits-categories__track" id="fpl-rail" tabindex="0">
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
            title="Toutes les pièces de <?php echo fpl_e($categorie_courante_nom); ?>">
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
        <div class="fpl-cat-item">
        <a href="<?php echo $lien; ?>"
            class="page-produits-cat-card <?php echo $actif ? 'is-active' : ''; ?>"
            title="<?php echo fpl_e($nom); ?>">
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
            <span class="page-produits-cat-card__name"><?php echo fpl_e($nom); ?></span>
        </a>
        <?php /* LE CRAYON ET LA POUBELLE SUR LA CARTE — geste de FPL natif :
                 corriger ou retirer une catégorie sans quitter le catalogue.
                 Seulement au premier niveau : ce dépôt n'a pas d'écran pour
                 modifier ou supprimer une SOUS-catégorie, et je n'accroche pas
                 un bouton à une page qui n'existe pas. */ ?>
        <?php if ($peut_gerer && !$dans_une_categorie): ?>
        <div class="fpl-cat-actions">
            <a class="fpl-cat-edit" href="../categories/modifier.php?id=<?php echo $cid; ?>"
                title="Modifier « <?php echo fpl_e($nom); ?> »" aria-label="Modifier <?php echo fpl_e($nom); ?>">
                <i class="fas fa-pen" aria-hidden="true"></i>
            </a>
            <a class="fpl-cat-edit fpl-cat-del" href="../categories/supprimer.php?id=<?php echo $cid; ?>"
                title="Supprimer « <?php echo fpl_e($nom); ?> »" aria-label="Supprimer <?php echo fpl_e($nom); ?>">
                <i class="fas fa-trash" aria-hidden="true"></i>
            </a>
        </div>
        <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php if ($peut_gerer): ?>
        <?php // La carte « + » ferme la marche : créer sans quitter la page. ?>
        <a href="<?php echo $dans_une_categorie
                ? '../stock/index.php'
                : '../categories/ajouter.php'; ?>"
            class="page-produits-cat-card fpl-cat-card--neuve"
            title="<?php echo $dans_une_categorie ? 'Créer une sous-catégorie' : 'Créer une catégorie'; ?>">
            <span class="page-produits-cat-card__img page-produits-cat-card__img--ph" aria-hidden="true">
                <i class="fas fa-plus"></i>
            </span>
            <span class="page-produits-cat-card__name"><?php echo $dans_une_categorie ? 'Nouvelle sous-catégorie' : 'Nouvelle catégorie'; ?></span>
        </a>
        <?php endif; ?>
    </div>
    <button type="button" class="fpl-rail-arrow" data-fpl-rail-dir="1" title="Suivant" aria-label="Faire défiler vers la droite">
        <i class="fas fa-chevron-right" aria-hidden="true"></i>
    </button>
    </div>
</section>
<script>
/* Défilement horizontal du bandeau — repris de FPL natif. Les flèches
   s'éteignent quand on atteint l'un des deux bouts. */
(function () {
    var rail = document.getElementById('fpl-rail');
    if (!rail) { return; }
    var fleches = document.querySelectorAll('.fpl-rail-arrow');
    fleches.forEach(function (btn) {
        btn.addEventListener('click', function () {
            rail.scrollBy({ left: parseInt(btn.dataset.fplRailDir, 10) * 340, behavior: 'smooth' });
        });
    });
    function majFleches() {
        var max = rail.scrollWidth - rail.clientWidth - 2;
        var g = document.querySelector('.fpl-rail-arrow[data-fpl-rail-dir="-1"]');
        var d = document.querySelector('.fpl-rail-arrow[data-fpl-rail-dir="1"]');
        // Tolérance de quelques pixels : le bandeau a une marge interne, donc
        // scrollLeft ne retombe jamais exactement à zéro.
        if (g) { g.disabled = rail.scrollLeft <= 4; }
        if (d) { d.disabled = rail.scrollLeft >= max - 4; }
    }
    rail.addEventListener('scroll', majFleches);
    window.addEventListener('resize', majFleches);
    majFleches();
    // Une seconde passe une fois la mise en page stabilisée (polices, images).
    window.addEventListener('load', majFleches);
})();
</script>
