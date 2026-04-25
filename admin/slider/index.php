<?php
/**
 * Page de liste des slides
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

// Afficher le message de succès s'il existe
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Récupérer tous les slides
require_once __DIR__ . '/../../models/model_slider.php';
$slides = get_all_slides(null); // Récupérer tous les slides (actifs et inactifs)
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du Slider - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-images"></i> Gestion du Slider</h1>
        <div class="header-actions">
            <a href="ajouter.php" class="btn-primary">
                <i class="fas fa-plus"></i> Nouveau Slide
            </a>
        </div>
    </div>

    <?php if (!empty($success_message)): ?>
    <div class="message success">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
    </div>
    <?php endif; ?>

    <section class="produits-section">
        <div class="section-title">
            <h2><i class="fas fa-images"></i> Slides du Carrousel (<?php echo count($slides); ?>)</h2>
        </div>

        <?php if (empty($slides)): ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <i class="fas fa-images" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
            <p>Aucun slide enregistré pour le moment.</p>
            <a href="ajouter.php" class="btn-primary" style="margin-top: 20px; display: inline-block;">
                <i class="fas fa-plus"></i> Ajouter le premier slide
            </a>
        </div>
        <?php else: ?>
        <div class="slides-grid">
            <?php foreach ($slides as $slide): ?>
            <div class="slide-card">
                <img src="/upload/slider/<?php echo htmlspecialchars($slide['image']); ?>"
                    alt="<?php echo htmlspecialchars($slide['titre']); ?>" class="slide-image"
                    onerror="this.src='/image/produit1.jpg'">
                <div class="slide-body">
                    <h3 class="slide-titre"><?php echo htmlspecialchars($slide['titre']); ?></h3>
                    <p class="slide-paragraphe"><?php echo htmlspecialchars($slide['paragraphe']); ?></p>
                    <div class="slide-info">
                        <span>Ordre: <?php echo $slide['ordre']; ?></span>
                        <span class="statut-badge statut-<?php echo $slide['statut']; ?>">
                            <?php echo ucfirst($slide['statut']); ?>
                        </span>
                    </div>
                    <?php if ($slide['bouton_texte']): ?>
                    <p class="slide-bouton-info">
                        <i class="fas fa-link"></i> Bouton: <?php echo htmlspecialchars($slide['bouton_texte']); ?>
                    </p>
                    <?php endif; ?>
                    <div class="slide-actions">
                        <a href="modifier.php?id=<?php echo $slide['id']; ?>" class="btn-card btn-edit">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        <a href="supprimer.php?id=<?php echo $slide['id']; ?>" class="btn-card btn-delete"
                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce slide ?');">
                            <i class="fas fa-trash"></i> Supprimer
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <?php include '../includes/footer.php'; ?>