<?php
/**
 * Page historique des mouvements de stock
 * Filtres: catégorie, produit, type
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../models/model_mouvements_stock.php';
require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_produits.php';

$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : null;
$produit_id = isset($_GET['produit_id']) ? (int) $_GET['produit_id'] : null;
$type_filter = isset($_GET['type']) && in_array($_GET['type'], ['entree', 'sortie', 'inventaire']) ? $_GET['type'] : null;

$mouvements = get_stock_mouvements(null, $produit_id, $categorie_id, $type_filter, 200);
$categories = get_all_categories();

if ($categorie_id > 0) {
    $produits = get_produits_by_categorie($categorie_id);
} else {
    $produits = get_all_produits();
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mouvements de Stock - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .mouvements-filters-card {
            background: linear-gradient(135deg, #fff 0%, #fafaf8 100%);
            border: 1px solid #e5e3d8;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        .mouvements-filters-card h3 {
            margin: 0 0 20px 0;
            font-size: 15px;
            color: #6b2f20;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 2px solid #918a44;
        }
        .mouvements-filters-card h3 i { color: #918a44; }
        .mouvements-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #6b2f20;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-group select {
            padding: 12px 14px;
            border: 2px solid #e5e3d8;
            border-radius: 10px;
            font-size: 14px;
            background: #fff;
            color: #333;
            transition: border-color 0.2s;
        }
        .filter-group select:focus {
            outline: none;
            border-color: #918a44;
        }
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-actions .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            background: #918a44;
            color: #fff;
            text-decoration: none;
        }
        .filter-actions .btn-primary:hover { background: #7a7340; }
        .filter-actions .btn-reset {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 18px;
            border-radius: 10px;
            font-weight: 600;
            border: 2px solid #e5e3d8;
            background: #fff;
            color: #6b2f20;
            text-decoration: none;
            transition: all 0.2s;
        }
        .filter-actions .btn-reset:hover {
            border-color: #918a44;
            background: #f8f7f2;
        }
        .mouvements-section {
            background: #fff;
            border: 1px solid #e5e3d8;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        .mouvements-section h2 {
            margin: 0;
            padding: 20px 24px;
            font-size: 16px;
            color: #6b2f20;
            background: #f8f7f2;
            border-bottom: 2px solid #e5e3d8;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .mouvements-section h2 i { color: #918a44; }
        .mouvements-table {
            width: 100%;
            border-collapse: collapse;
        }
        .mouvements-table th, .mouvements-table td {
            padding: 14px 20px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .mouvements-table th {
            background: #f8f8f8;
            font-weight: 600;
            color: #6b2f20;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .mouvements-table tbody tr:hover { background: #fafaf8; }
        .badge-entree { background: #d4edda; color: #155724; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; }
        .badge-sortie { background: #f8d7da; color: #721c24; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; }
        .badge-inventaire { background: #fff3cd; color: #856404; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; }
        .empty-state {
            padding: 48px 24px;
            text-align: center;
            color: #666;
        }
        .empty-state i { font-size: 48px; color: #ccc; margin-bottom: 16px; display: block; }
        /* Responsive: cartes sur mobile */
        .mouvements-cards { display: none; }
        .mouvement-card {
            background: #fff;
            border: 1px solid #e5e3d8;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .mouvement-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .mouvement-card-date { font-size: 13px; color: #666; font-weight: 600; }
        .mouvement-card-body { display: grid; gap: 8px; }
        .mouvement-card-row { display: flex; justify-content: space-between; font-size: 13px; }
        .mouvement-card-row .label { color: #888; }
        .mouvement-card-row .value { font-weight: 600; color: #333; }
        .mouvement-card-notes { font-size: 12px; color: #666; margin-top: 8px; padding-top: 8px; border-top: 1px dashed #eee; }
        @media (max-width: 768px) {
            .mouvements-table-wrap { display: none !important; }
            .mouvements-cards { display: block; padding: 16px; }
        }
        @media (min-width: 769px) {
            .mouvements-cards { display: none !important; }
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-history"></i> Historique des mouvements de stock</h1>
        <div class="header-actions">
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Retour au stock
            </a>
        </div>
    </div>

    <section class="produits-section">
        <div class="mouvements-filters-card">
            <h3><i class="fas fa-filter"></i> Filtres</h3>
            <form method="GET" action="">
                <div class="mouvements-filters">
                    <div class="filter-group">
                        <label for="categorie_id"><i class="fas fa-tags"></i> Catégorie</label>
                        <select name="categorie_id" id="categorie_id">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?php echo (int) $c['id']; ?>" <?php echo $categorie_id === (int) $c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['nom']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="produit_id"><i class="fas fa-box"></i> Produit</label>
                        <select name="produit_id" id="produit_id">
                            <option value="">Tous les produits</option>
                            <?php foreach ($produits as $p): ?>
                            <option value="<?php echo (int) $p['id']; ?>" <?php echo $produit_id === (int) $p['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['nom']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="type"><i class="fas fa-exchange-alt"></i> Type</label>
                        <select name="type" id="type">
                            <option value="">Tous les types</option>
                            <option value="entree" <?php echo $type_filter === 'entree' ? 'selected' : ''; ?>>Entrées</option>
                            <option value="sortie" <?php echo $type_filter === 'sortie' ? 'selected' : ''; ?>>Sorties</option>
                            <option value="inventaire" <?php echo $type_filter === 'inventaire' ? 'selected' : ''; ?>>Inventaires</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-search"></i> Filtrer
                        </button>
                        <a href="mouvements.php" class="btn-reset">
                            <i class="fas fa-rotate-left"></i> Réinitialiser
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div class="mouvements-section">
            <h2><i class="fas fa-list"></i> Mouvements (<?php echo count($mouvements); ?>)</h2>
            <?php if (empty($mouvements)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <p>Aucun mouvement enregistré<?php echo ($categorie_id || $produit_id || $type_filter) ? ' pour ces critères.' : '.'; ?></p>
                    <?php if ($categorie_id || $produit_id || $type_filter): ?>
                    <a href="mouvements.php" class="btn-primary" style="margin-top: 12px; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fas fa-rotate-left"></i> Voir tous les mouvements
                    </a>
                    <?php else: ?>
                    <a href="index.php" class="btn-primary" style="margin-top: 12px; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fas fa-arrow-left"></i> Retour au stock
                    </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="mouvements-table-wrap" style="overflow-x: auto;">
                    <table class="mouvements-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Article / Produit</th>
                                <th>Quantité</th>
                                <th>Avant</th>
                                <th>Après</th>
                                <th>Référence</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mouvements as $m): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($m['date_mouvement'])); ?></td>
                                    <td>
                                        <?php
                                        $badge = 'badge-' . $m['type'];
                                        $label = $m['type'] === 'entree' ? 'Entrée' : ($m['type'] === 'sortie' ? 'Sortie' : 'Inventaire');
                                        ?>
                                        <span class="<?php echo $badge; ?>"><?php echo $label; ?></span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($m['produit_nom'] ?? '-'); ?>
                                    </td>
                                    <td><?php echo (int) $m['quantite']; ?></td>
                                    <td><?php echo $m['quantite_avant'] !== null ? (int) $m['quantite_avant'] : '-'; ?></td>
                                    <td><?php echo $m['quantite_apres'] !== null ? (int) $m['quantite_apres'] : '-'; ?></td>
                                    <td>
                                        <?php
                                        if (!empty($m['reference_numero'])) {
                                            echo htmlspecialchars($m['reference_numero']);
                                        } elseif ($m['reference_type'] === 'commande' && $m['reference_id']) {
                                            echo 'Commande #' . (int) $m['reference_id'];
                                        } else {
                                            echo htmlspecialchars($m['reference_type'] ?? '-');
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($m['notes'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mouvements-cards">
                    <?php foreach ($mouvements as $m):
                        $badge = 'badge-' . $m['type'];
                        $label = $m['type'] === 'entree' ? 'Entrée' : ($m['type'] === 'sortie' ? 'Sortie' : 'Inventaire');
                        $ref = !empty($m['reference_numero']) ? htmlspecialchars($m['reference_numero']) : ($m['reference_type'] === 'commande' && $m['reference_id'] ? 'Commande #' . (int) $m['reference_id'] : htmlspecialchars($m['reference_type'] ?? '-'));
                    ?>
                    <div class="mouvement-card">
                        <div class="mouvement-card-header">
                            <span class="mouvement-card-date"><i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y H:i', strtotime($m['date_mouvement'])); ?></span>
                            <span class="<?php echo $badge; ?>"><?php echo $label; ?></span>
                        </div>
                        <div class="mouvement-card-body">
                            <div class="mouvement-card-row">
                                <span class="label">Article / Produit</span>
                                <span class="value"><?php echo htmlspecialchars($m['produit_nom'] ?? '-'); ?></span>
                            </div>
                            <div class="mouvement-card-row">
                                <span class="label">Quantité</span>
                                <span class="value"><?php echo (int) $m['quantite']; ?></span>
                            </div>
                            <div class="mouvement-card-row">
                                <span class="label">Avant</span>
                                <span class="value"><?php echo $m['quantite_avant'] !== null ? (int) $m['quantite_avant'] : '-'; ?></span>
                            </div>
                            <div class="mouvement-card-row">
                                <span class="label">Après</span>
                                <span class="value"><?php echo $m['quantite_apres'] !== null ? (int) $m['quantite_apres'] : '-'; ?></span>
                            </div>
                            <div class="mouvement-card-row">
                                <span class="label">Référence</span>
                                <span class="value"><?php echo $ref; ?></span>
                            </div>
                        </div>
                        <?php if (!empty($m['notes'])): ?>
                        <div class="mouvement-card-notes"><?php echo htmlspecialchars($m['notes']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>
</body>

</html>
