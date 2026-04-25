<?php
/**
 * Migration web : ajout de la colonne image_reference sur commandes_personnalisees
 * À exécuter une fois via : http://localhost:5000/run_migration_image_cp.php
 * Supprimer ce fichier après exécution pour des raisons de sécurité.
 */
session_start();
require_once __DIR__ . '/conn/conn.php';

$done = false;
$message = '';
$error = '';

if (isset($db) && $db instanceof PDO) {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM commandes_personnalisees LIKE 'image_reference'");
        if ($stmt && $stmt->rowCount() > 0) {
            $message = 'La colonne image_reference existe déjà.';
            $done = true;
        } else {
            $db->exec("ALTER TABLE commandes_personnalisees ADD COLUMN image_reference VARCHAR(255) NULL DEFAULT NULL AFTER description");
            $message = 'Colonne image_reference ajoutée avec succès.';
            $done = true;
        }
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
} else {
    $error = 'Connexion à la base de données indisponible.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration image_reference</title>
    <style>
        body { font-family: sans-serif; max-width: 500px; margin: 60px auto; padding: 24px; background: #f5f5f5; }
        .box { background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .success { color: #0a7; }
        .error { color: #c00; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Migration image_reference</h2>
        <?php if ($done): ?>
            <p class="success"><?php echo htmlspecialchars($message); ?></p>
        <?php elseif ($error): ?>
            <p class="error">Erreur : <?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <p><a href="index.php">Retour à l'accueil</a></p>
    </div>
</body>
</html>
