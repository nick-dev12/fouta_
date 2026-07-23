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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-parametres-page.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-parametres-admin">
    <?php include 'includes/nav.php'; ?>

    <section class="produits-section parametres-page">
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
                        <span class="parametre-link__txt"><i class="fas fa-map-pin" aria-hidden="true"></i> Structure par étage (rayons, allées…)</span>
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
    </section>

    <?php include 'includes/footer.php'; ?>
</body>

</html>