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
            <p class="parametres-hero__lead">Personnalisez l’apparence et les contenus affichés sur la boutique (bannière, médias, logos).</p>
        </header>

        <?php if (!empty($success_message)): ?>
            <div class="message success" role="status">
                <i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <div class="parametres-grid" role="list">
            <article class="parametre-card parametre-card--banner" role="listitem">
                <div class="parametre-card__body">
                    <div class="parametre-card__head">
                        <div class="parametre-icon" aria-hidden="true"><i class="fas fa-home"></i></div>
                        <h3 class="parametre-title">Bannière d'Accueil</h3>
                    </div>
                    <a href="parametres/section4.php" class="parametre-link">
                        <span class="parametre-link__txt"><i class="fas fa-pen-to-square" aria-hidden="true"></i> Modifier la bannière</span>
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
                        <span class="parametre-link__txt"><i class="fas fa-pen-to-square" aria-hidden="true"></i> Gérer le slider</span>
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
                        <span class="parametre-link__txt"><i class="fas fa-pen-to-square" aria-hidden="true"></i> Gérer les vidéos</span>
                        <i class="fas fa-chevron-right parametre-link__chev" aria-hidden="true"></i>
                    </a>
                </div>
            </article>

            <article class="parametre-card parametre-card--logos" role="listitem">
                <div class="parametre-card__body">
                    <div class="parametre-card__head">
                        <div class="parametre-icon" aria-hidden="true"><i class="fas fa-images"></i></div>
                        <h3 class="parametre-title">Logos Partenaires</h3>
                    </div>
                    <a href="parametres/logos.php" class="parametre-link">
                        <span class="parametre-link__txt"><i class="fas fa-pen-to-square" aria-hidden="true"></i> Gérer les logos</span>
                        <i class="fas fa-chevron-right parametre-link__chev" aria-hidden="true"></i>
                    </a>
                </div>
            </article>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
</body>

</html>