<?php
/**
 * Script de migration : ajoute les colonnes couleurs et taille à la table produits
 * À exécuter une seule fois : /admin/run_migration_produits.php
 */
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../conn/conn.php';

$messages = [];
$ok = true;

try {
    $stmt = $db->query("SHOW COLUMNS FROM produits LIKE 'couleurs'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE produits ADD COLUMN couleurs VARCHAR(255) NULL DEFAULT NULL");
        $messages[] = 'Colonne "couleurs" ajoutée.';
    } else {
        $messages[] = 'Colonne "couleurs" existe déjà.';
    }
} catch (Exception $e) {
    $messages[] = 'Erreur couleurs: ' . $e->getMessage();
    $ok = false;
}

try {
    $stmt = $db->query("SHOW COLUMNS FROM produits LIKE 'taille'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE produits ADD COLUMN taille VARCHAR(255) NULL DEFAULT NULL");
        $messages[] = 'Colonne "taille" ajoutée.';
    } else {
        $messages[] = 'Colonne "taille" existe déjà.';
    }
} catch (Exception $e) {
    $messages[] = 'Erreur taille: ' . $e->getMessage();
    $ok = false;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <title>Migration produits</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    <div class="contents-container" style="padding: 40px;">
        <h1><i class="fas fa-database"></i> Migration - Colonnes produits</h1>
        <div class="message <?php echo $ok ? 'success' : 'error'; ?>" style="margin-top: 20px;">
            <?php foreach ($messages as $m): ?>
                <p><?php echo htmlspecialchars($m); ?></p>
            <?php endforeach; ?>
        </div>
        <a href="produits/index.php" class="btn-primary" style="margin-top: 20px;">
            <i class="fas fa-arrow-left"></i> Retour aux produits
        </a>
    </div>
</body>
</html>
