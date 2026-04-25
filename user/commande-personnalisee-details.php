<?php
/**
 * Détails d'une commande personnalisée (côté client)
 */

require_once __DIR__ . '/../includes/session_user.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_email'])) {
    header('Location: connexion.php');
    exit;
}

$cp_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($cp_id <= 0) {
    header('Location: mes-commandes.php');
    exit;
}

require_once __DIR__ . '/../models/model_commandes_personnalisees.php';
$cp = get_commande_personnalisee_by_id($cp_id);

if (!$cp || $cp['user_id'] != $_SESSION['user_id']) {
    header('Location: mes-commandes.php');
    exit;
}

$statuts_labels = get_statuts_commande_personnalisee();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Demande #<?php echo $cp['id']; ?> - FOUTA POIDS LOURDS</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/user-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include 'includes/user_nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-palette"></i> Ma commande personnalisée</h1>
        <div class="header-actions">
            <a href="mes-commandes.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour</a>
        </div>
    </div>

    <section class="content-section">
        <div class="commande-perso-detail-card">
            <div class="cp-detail-header">
                <h2>Demande #<?php echo $cp['id']; ?></h2>
                <span class="commande-statut statut-<?php echo $cp['statut']; ?>">
                    <?php echo $statuts_labels[$cp['statut']] ?? $cp['statut']; ?>
                </span>
            </div>
            <div class="cp-detail-body">
                <div class="detail-item">
                    <label>Description</label>
                    <div class="value"><?php echo nl2br(htmlspecialchars($cp['description'])); ?></div>
                </div>
                <?php if ($cp['type_produit']): ?>
                <div class="detail-item">
                    <label>Type de produit</label>
                    <div class="value"><?php echo htmlspecialchars($cp['type_produit']); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($cp['quantite']): ?>
                <div class="detail-item">
                    <label>Quantité souhaitée</label>
                    <div class="value"><?php echo htmlspecialchars($cp['quantite']); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($cp['date_souhaitee']): ?>
                <div class="detail-item">
                    <label>Date souhaitée</label>
                    <div class="value"><?php echo date('d/m/Y', strtotime($cp['date_souhaitee'])); ?></div>
                </div>
                <?php endif; ?>
                <div class="detail-item">
                    <label>Date de demande</label>
                    <div class="value"><?php echo date('d/m/Y à H:i', strtotime($cp['date_creation'])); ?></div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/user_footer.php'; ?>
</body>
</html>
