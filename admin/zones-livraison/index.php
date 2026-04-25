<?php
session_start();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
require_once __DIR__ . '/../../models/model_zones_livraison.php';
$zones = get_all_zones_livraison(null);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zones de livraison - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-top: 20px;
        }
        .zones-table { width: 100%; min-width: 640px; border-collapse: collapse; }
        .zones-table th, .zones-table td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #e8e8e8; }
        .zones-table th { background: #ffffff; color: #6b2f20; font-weight: 600; }
        .zones-table tr:hover { background: #fafafa; }
        .zone-prix { font-weight: 600; color: #918a44; }
        .zone-lieu { font-weight: 500; color: #000; }
        .statut-badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .statut-actif { background: #e8f5e9; color: #2e7d32; }
        .statut-inactif { background: #ffebee; color: #c62828; }
        .zones-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .zones-actions a { padding: 6px 12px; border-radius: 6px; font-size: 13px; text-decoration: none; }
        .btn-edit { background: #e3f2fd; color: #1565c0; }
        .btn-edit:hover { background: #bbdefb; }
        .btn-delete { background: #ffebee; color: #c62828; }
        .btn-delete:hover { background: #ffcdd2; }
        @media (max-width: 768px) {
            .content-header { flex-direction: column; align-items: stretch; gap: 12px; }
            .content-header .header-actions .btn-primary { width: 100%; justify-content: center; }
            .section-title { flex-direction: column; align-items: flex-start; gap: 8px; }
            .zones-table th, .zones-table td { padding: 10px 12px; font-size: 14px; }
            .zones-actions { flex-direction: column; }
            .zones-actions a { width: 100%; text-align: center; justify-content: center; }
        }
        @media (max-width: 480px) {
            .zones-table th, .zones-table td { padding: 8px 10px; font-size: 13px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/nav.php'; ?>
    <div class="content-header">
        <h1><i class="fas fa-truck"></i> Zones de livraison</h1>
        <div class="header-actions">
            <a href="ajouter.php" class="btn-primary"><i class="fas fa-plus"></i> Nouvelle zone</a>
        </div>
    </div>
    <?php if (!empty($success_message)): ?>
        <div class="message success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <section class="produits-section">
        <div class="section-title">
            <h2><i class="fas fa-map-marker-alt"></i> Lieux et tarifs de livraison (<?php echo count($zones); ?>)</h2>
        </div>
        <?php if (empty($zones)): ?>
            <div class="empty-state">
                <i class="fas fa-truck"></i>
                <h3>Aucune zone de livraison</h3>
                <p>Définissez les zones (ville, quartier) et les prix de livraison pour que les clients puissent sélectionner leur adresse lors de la commande.</p>
                <a href="ajouter.php" class="btn-primary"><i class="fas fa-plus"></i> Ajouter la première zone</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="zones-table">
                    <thead>
                        <tr>
                            <th>Ville</th>
                            <th>Quartier / Zone</th>
                            <th>Prix livraison (FCFA)</th>
                            <th>Description</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($zones as $zone): ?>
                        <tr>
                            <td class="zone-lieu"><?php echo htmlspecialchars($zone['ville']); ?></td>
                            <td class="zone-lieu"><?php echo htmlspecialchars($zone['quartier']); ?></td>
                            <td class="zone-prix"><?php echo number_format($zone['prix_livraison'], 0, ',', ' '); ?> FCFA</td>
                            <td><?php echo htmlspecialchars($zone['description'] ?? '-'); ?></td>
                            <td><span class="statut-badge <?php echo $zone['statut'] === 'actif' ? 'statut-actif' : 'statut-inactif'; ?>"><?php echo $zone['statut'] === 'actif' ? 'Actif' : 'Inactif'; ?></span></td>
                            <td>
                                <div class="zones-actions">
                                    <a href="modifier.php?id=<?php echo $zone['id']; ?>" class="btn-edit"><i class="fas fa-edit"></i> Modifier</a>
                                    <a href="supprimer.php?id=<?php echo $zone['id']; ?>" class="btn-delete" onclick="return confirm('Supprimer cette zone ?');"><i class="fas fa-trash"></i> Supprimer</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php include '../includes/footer.php'; ?>
