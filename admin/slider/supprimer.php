<?php
/**
 * Page de suppression d'un slide
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer l'ID du slide
$slide_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($slide_id <= 0) {
    header('Location: index.php');
    exit;
}

// Récupérer le slide
require_once __DIR__ . '/../../models/model_slider.php';
$slide = get_slide_by_id($slide_id);

if (!$slide) {
    header('Location: index.php');
    exit;
}

// Traiter la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    require_once __DIR__ . '/../../controllers/controller_slider.php';
    $result = process_delete_slide($slide_id);
    
    if ($result['success']) {
        $_SESSION['success_message'] = $result['message'];
        header('Location: index.php');
        exit;
    } else {
        $error_message = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer un Slide - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include '../includes/nav.php'; ?>
    
    <div class="content-header">
        <h1><i class="fas fa-trash"></i> Supprimer un Slide</h1>
        <div class="header-actions">
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <section class="content-section">
        <?php if (isset($error_message)): ?>
            <div style="background: #fee; border-left: 4px solid #c26638; color: #6b2f20; padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div style="text-align: center; padding: 20px;">
            <img src="../../upload/slider/<?php echo htmlspecialchars($slide['image']); ?>" 
                 alt="<?php echo htmlspecialchars($slide['titre']); ?>" 
                 style="max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px;"
                 onerror="this.src='../../image/produit1.jpg'">
            
            <h2 style="color: #6b2f20; margin-bottom: 10px;"><?php echo htmlspecialchars($slide['titre']); ?></h2>
            <p style="color: #666; margin-bottom: 30px;"><?php echo htmlspecialchars($slide['paragraphe']); ?></p>

            <div style="background: #fee; border: 2px solid #c26638; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
                <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #c26638; margin-bottom: 15px;"></i>
                <h3 style="color: #6b2f20; margin-bottom: 10px;">Attention !</h3>
                <p style="color: #000000;">Vous êtes sur le point de supprimer ce slide. Cette action est irréversible.</p>
            </div>

            <form method="POST" action="" style="display: inline-block;">
                <button type="submit" name="confirm_delete" class="btn-card btn-delete" style="padding: 12px 30px; font-size: 16px;">
                    <i class="fas fa-trash"></i> Confirmer la suppression
                </button>
            </form>
            <a href="index.php" class="btn-back" style="margin-left: 15px; padding: 12px 30px; display: inline-block;">
                Annuler
            </a>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>

