<?php
/**
 * Page principale des paramètres - Regroupe toutes les configurations
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/require_access.php';
require_once __DIR__ . '/../includes/admin_permissions.php';
$parametres_role = admin_current_role();
$parametres_stock_etendu = admin_can_gestion_stock_etendue() && $parametres_role === 'gestion_stock_general';

// Afficher le message de succès s'il existe
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Administration</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
<?php include __DIR__ . '/includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-parametres-page.css'); ?>
</head>

<body class="page-parametres-admin">
    <?php include 'includes/nav.php'; ?>

    <section class="produits-section parametres-page">
        <?php if ($parametres_stock_etendu): ?>
        <?php /* =============================================================
                 PARAMÈTRES STOCK — refonte du 31/08/2026.
                 L'ancien écran empruntait le titre du site (« Paramètres —
                 configuration du site ») et annonçait des champs produit que
                 ce profil ne peut pas ouvrir. Ses libellés parlaient technique
                 (« éléments nommés », « hiérarchie (niveaux) ») dans une
                 écriture de 11 px, plus petite que partout ailleurs.
                 Ici : le vrai nom de la page, une tuile par destination, dite
                 en français de métier, à la taille du reste du logiciel.
                 ============================================================= */ ?>
        <div class="page-lead">
            <div>
                <h1 class="page-lead__title">Paramètres stock</h1>
                <p class="page-lead__sub">
                    Ce qui règle le magasin : où les pièces se rangent, quand l'alerte parle,
                    et à quelle taille s'impriment les étiquettes.
                </p>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" role="status"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <h2 class="param-stock-groupe">Le rangement</h2>
        <div class="param-stock-grille">
            <a class="param-stock-tuile" href="parametres/hierarchie-entrepot.php">
                <span class="param-stock-tuile__ico" aria-hidden="true"><i class="fas fa-sitemap"></i></span>
                <span class="param-stock-tuile__txt">
                    <strong>Structure de l'entrepôt</strong>
                    <span>Les niveaux du rangement — étage, zone, rayon, étagère, barre. Les créer, les renommer, les remettre en ordre.</span>
                </span>
                <i class="fas fa-chevron-right param-stock-tuile__chev" aria-hidden="true"></i>
            </a>

            <a class="param-stock-tuile" href="parametres/emplacement-entrepot.php">
                <span class="param-stock-tuile__ico" aria-hidden="true"><i class="fas fa-map-pin"></i></span>
                <span class="param-stock-tuile__txt">
                    <strong>Emplacements, étage par étage</strong>
                    <span>Nommer chaque élément d'un étage, du niveau jusqu'à la position, onglet par onglet.</span>
                </span>
                <i class="fas fa-chevron-right param-stock-tuile__chev" aria-hidden="true"></i>
            </a>
        </div>

        <h2 class="param-stock-groupe">Les alertes</h2>
        <div class="param-stock-grille">
            <a class="param-stock-tuile" href="parametres/alertes-stock.php">
                <span class="param-stock-tuile__ico" aria-hidden="true"><i class="fas fa-bell"></i></span>
                <span class="param-stock-tuile__txt">
                    <strong>Seuils d'alerte</strong>
                    <span>Les règles par rayon, le seuil propre à une pièce, et ce que les ventes conseillent. L'alerte parle dès que le stock est inférieur ou égal au seuil.</span>
                </span>
                <i class="fas fa-chevron-right param-stock-tuile__chev" aria-hidden="true"></i>
            </a>

            <a class="param-stock-tuile" href="produits/seuils-rayon.php">
                <span class="param-stock-tuile__ico" aria-hidden="true"><i class="fas fa-list-check"></i></span>
                <span class="param-stock-tuile__txt">
                    <strong>Seuils, rayon par rayon</strong>
                    <span>Poser le seuil de cinquante pièces d'un seul enregistrement, avec leur stock du jour sous les yeux.</span>
                </span>
                <i class="fas fa-chevron-right param-stock-tuile__chev" aria-hidden="true"></i>
            </a>
        </div>

        <h2 class="param-stock-groupe">Les étiquettes</h2>
        <div class="param-stock-grille">
            <a class="param-stock-tuile" href="parametres/etiquettes-produit.php">
                <span class="param-stock-tuile__ico" aria-hidden="true"><i class="fas fa-tag"></i></span>
                <span class="param-stock-tuile__txt">
                    <strong>Étiquettes de pièce</strong>
                    <span>Les tailles proposées à l'impression — 70 × 70, 50 × 30, 65 × 100, 100 × 130 mm — et celle qui sert par défaut.</span>
                </span>
                <i class="fas fa-chevron-right param-stock-tuile__chev" aria-hidden="true"></i>
            </a>

            <a class="param-stock-tuile" href="parametres/etiquettes-entrepot.php">
                <span class="param-stock-tuile__ico" aria-hidden="true"><i class="fas fa-map-pin"></i></span>
                <span class="param-stock-tuile__txt">
                    <strong>Étiquettes de barre</strong>
                    <span>Les dimensions des étiquettes collées sur le rangement lui-même, et la place de chaque élément dessus.</span>
                </span>
                <i class="fas fa-chevron-right param-stock-tuile__chev" aria-hidden="true"></i>
            </a>
        </div>

        <?php else: ?>
        <header class="parametres-hero">
            <p class="parametres-hero__eyebrow">Configuration du site</p>
            <h1 class="parametres-hero__title"><i class="fas fa-sliders" aria-hidden="true"></i> Paramètres</h1>
            <p class="parametres-hero__lead"><?php echo $parametres_stock_etendu
                ? 'Configuration entrepôt, alertes de stock et champs produit.'
                : 'Personnalisez l’apparence et les contenus affichés sur la boutique (bannière, médias, logos).'; ?></p>
        </header>

        <?php if (!empty($success_message)): ?>
            <div class="message success" role="status">
                <i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <div class="parametres-grid" role="list">
            <?php if (!$parametres_stock_etendu): ?>
            <article class="parametre-card parametre-card--banner" role="listitem">
                <div class="parametre-card__body">
                    <div class="parametre-card__head">
                        <div class="parametre-icon" aria-hidden="true"><i class="fas fa-home"></i></div>
                        <h3 class="parametre-title">Bannière d'Accueil</h3>
                    </div>
                    <a href="parametres/section4.php" class="parametre-link">
                        <span class="parametre-link__txt"><i class="fas fa-pen-to-square" aria-hidden="true"></i>
                            Modifier la bannière</span>
                        <i class="fas fa-chevron-right parametre-link__chev" aria-hidden="true"></i>
                    </a>
                </div>
            </article>

            <article class="parametre-card parametre-card--affiche" role="listitem">
                <div class="parametre-card__body">
                    <div class="parametre-card__head">
                        <div class="parametre-icon" aria-hidden="true"><i class="fas fa-sliders-h"></i></div>
                        <h3 class="parametre-title">Image d'affiche</h3>
                    </div>
                    <a href="slider/index.php" class="parametre-link">
                        <span class="parametre-link__txt"><i class="fas fa-pen-to-square" aria-hidden="true"></i> Gérer
                            le slider</span>
                        <i class="fas fa-chevron-right parametre-link__chev" aria-hidden="true"></i>
                    </a>
                </div>
            </article>

            <article class="parametre-card parametre-card--videos" role="listitem">
                <div class="parametre-card__body">
                    <div class="parametre-card__head">
                        <div class="parametre-icon" aria-hidden="true"><i class="fas fa-video"></i></div>
                        <h3 class="parametre-title">Section Vidéos</h3>
                    </div>
                    <a href="parametres/videos.php" class="parametre-link">
                        <span class="parametre-link__txt"><i class="fas fa-pen-to-square" aria-hidden="true"></i> Gérer
                            les vidéos</span>
                        <i class="fas fa-chevron-right parametre-link__chev" aria-hidden="true"></i>
                    </a>
                </div>
            </article>

            <article class="parametre-card parametre-card--logos" role="listitem">
                <div class="parametre-card__body">
                    <div class="parametre-card__head">
                        <div class="parametre-icon" aria-hidden="true"><i class="fas fa-images"></i></div>
                        <h3 class="parametre-title">Logos, marques &amp; fournisseurs</h3>
                    </div>
                    <a href="parametres/logos.php" class="parametre-link">
                        <span class="parametre-link__txt"><i class="fas fa-pen-to-square" aria-hidden="true"></i> Gérer
                            logos, marques &amp; fournisseurs</span>
                        <i class="fas fa-chevron-right parametre-link__chev" aria-hidden="true"></i>
                    </a>
                </div>
            </article>

            <?php endif; ?>

            <article class="parametre-card parametre-card--emplacement-entrepot" role="listitem">
                <div class="parametre-card__body">
                    <div class="parametre-card__head">
                        <div class="parametre-icon" aria-hidden="true"><i class="fas fa-warehouse"></i></div>
                        <h3 class="parametre-title">Emplacement entrepôt</h3>
                    </div>
                    <a href="parametres/emplacement-entrepot.php" class="parametre-link">
                        <span class="parametre-link__txt"><i class="fas fa-map-pin" aria-hidden="true"></i> Structure par étage (éléments nommés)</span>
                        <i class="fas fa-chevron-right parametre-link__chev" aria-hidden="true"></i>
                    </a>
                    <a href="parametres/hierarchie-entrepot.php" class="parametre-link">
                        <span class="parametre-link__txt"><i class="fas fa-sitemap" aria-hidden="true"></i> Configurer la hiérarchie (niveaux)</span>
                        <i class="fas fa-chevron-right parametre-link__chev" aria-hidden="true"></i>
                    </a>
                    <a href="parametres/etiquettes-entrepot.php" class="parametre-link">
                        <span class="parametre-link__txt"><i class="fas fa-tags" aria-hidden="true"></i> Dimensions d’impression des étiquettes</span>
                        <i class="fas fa-chevron-right parametre-link__chev" aria-hidden="true"></i>
                    </a>
                </div>
            </article>

            <?php
            require_once __DIR__ . '/../includes/admin_permissions.php';
            require_once __DIR__ . '/../models/model_produit_formulaire_champs.php';
            if (admin_is_full_admin() || produit_formulaire_peut_gerer_champs()):
            ?>
            <article class="parametre-card parametre-card--champs-produit" role="listitem">
                <div class="parametre-card__body">
                    <div class="parametre-card__head">
                        <div class="parametre-icon" aria-hidden="true"><i class="fas fa-list-check"></i></div>
                        <h3 class="parametre-title">Champs formulaire produit</h3>
                    </div>
                    <a href="parametres/champs-produit.php" class="parametre-link">
                        <span class="parametre-link__txt"><i class="fas fa-sliders-h" aria-hidden="true"></i> Ajout / modification — champs dynamiques &amp; droits</span>
                        <i class="fas fa-chevron-right parametre-link__chev" aria-hidden="true"></i>
                    </a>
                    <p class="parametre-card__hint">Les champs actifs et autorisés pour chaque profil déterminent aussi les colonnes disponibles dans le <strong>suivi catalogue</strong>.</p>
                </div>
            </article>
            <?php endif; ?>

            <article class="parametre-card parametre-card--alertes-stock" role="listitem">
                <div class="parametre-card__body">
                    <div class="parametre-card__head">
                        <div class="parametre-icon" aria-hidden="true"><i class="fas fa-bell"></i></div>
                        <h3 class="parametre-title">Alertes de stock</h3>
                    </div>
                    <a href="parametres/alertes-stock.php" class="parametre-link">
                        <span class="parametre-link__txt"><i class="fas fa-sliders-h" aria-hidden="true"></i> Seuils
                            standard / moyen / haut</span>
                        <i class="fas fa-chevron-right parametre-link__chev" aria-hidden="true"></i>
                    </a>
                </div>
            </article>

            <article class="parametre-card parametre-card--etiquettes-produit" role="listitem">
                <div class="parametre-card__body">
                    <div class="parametre-card__head">
                        <div class="parametre-icon" aria-hidden="true"><i class="fas fa-tag"></i></div>
                        <h3 class="parametre-title">Étiquettes produit (FPL)</h3>
                    </div>
                    <a href="parametres/etiquettes-produit.php" class="parametre-link">
                        <span class="parametre-link__txt"><i class="fas fa-ruler-combined" aria-hidden="true"></i> Dimensions d’impression (défaut 70×70 mm)</span>
                        <i class="fas fa-chevron-right parametre-link__chev" aria-hidden="true"></i>
                    </a>
                    <p class="parametre-card__hint">Appliqué à l’aperçu et à l’impression sur la page ajuster stock.</p>
                </div>
            </article>

            <?php if (!$parametres_stock_etendu): ?>
            <article class="parametre-card parametre-card--bulletin-paie" role="listitem">
                <div class="parametre-card__body">
                    <div class="parametre-card__head">
                        <div class="parametre-icon" aria-hidden="true"><i class="fas fa-file-invoice-dollar"></i></div>
                        <h3 class="parametre-title">Bulletins de paie (RH)</h3>
                    </div>
                    <a href="parametres/bulletin_paie.php" class="parametre-link">
                        <span class="parametre-link__txt"><i class="fas fa-sliders-h" aria-hidden="true"></i> Employeur,
                            rubriques &amp; mentions</span>
                        <i class="fas fa-chevron-right parametre-link__chev" aria-hidden="true"></i>
                    </a>
                </div>
            </article>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>

    <?php include 'includes/footer.php'; ?>
</body>

</html>