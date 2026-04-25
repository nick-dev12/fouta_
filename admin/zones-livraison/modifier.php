<?php
/**
 * Page de modification de zone de livraison
 * Programmation procédurale uniquement
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

$zone_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($zone_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_zones_livraison.php';
$zone = get_zone_livraison_by_id($zone_id);
if (!$zone) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../controllers/controller_zones_livraison.php';
$result = process_update_zone_livraison($zone_id);

if (isset($result['success']) && $result['success']) {
    $_SESSION['success_message'] = $result['message'];
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier une zone de livraison - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .form-container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; color: #6b2f20; margin-bottom: 8px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px 15px; border: 2px solid #e8e8e8; border-radius: 8px; font-size: 14px; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #918a44; }
        .error-message { background: #fee; border-left: 4px solid #c26638; color: #6b2f20; padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; }
        .btn-back { background: #e0e0e0; color: #6b2f20; padding: 10px 20px; border: none; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-back:hover { background: #d0d0d0; }
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>
    
    <div class="content-header">
        <h1><i class="fas fa-edit"></i> Modifier la zone</h1>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <div class="form-container">
        <?php if (isset($result['message']) && !empty($result['message']) && !$result['success']): ?>
            <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($result['message']); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="ville">Ville *</label>
                <input type="text" id="ville" name="ville" required 
                       value="<?php echo htmlspecialchars($zone['ville']); ?>">
            </div>
            <div class="form-group">
                <label for="quartier">Quartier / Zone *</label>
                <input type="text" id="quartier" name="quartier" required 
                       value="<?php echo htmlspecialchars($zone['quartier']); ?>">
            </div>
            <div class="form-group">
                <label for="prix_livraison">Prix de livraison (FCFA) *</label>
                <input type="number" id="prix_livraison" name="prix_livraison" required min="0" step="100" 
                       value="<?php echo htmlspecialchars($zone['prix_livraison']); ?>">
            </div>
            <div class="form-group">
                <label for="description">Description (optionnel)</label>
                <textarea id="description" name="description" rows="2"><?php echo htmlspecialchars($zone['description'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="statut">Statut</label>
                <select id="statut" name="statut">
                    <option value="actif" <?php echo $zone['statut'] === 'actif' ? 'selected' : ''; ?>>Actif</option>
                    <option value="inactif" <?php echo $zone['statut'] === 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                </select>
                <small style="color:#666;">Les zones inactives ne sont pas proposées aux clients.</small>
            </div>
            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
        </form>
    </div>

    <?php include '../includes/footer.php'; ?>
