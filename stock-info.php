<?php
/**
 * Page publique affichée lors du scan du QR code d'un produit
 * Affiche les détails de gestion du stock : nombre vendu, restant, total avant
 * Accessible sans authentification
 */

require_once __DIR__ . '/includes/session_user.php';
session_start();

$produit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($produit_id <= 0) {
    header('Location: /');
    exit;
}

require_once __DIR__ . '/conn/conn.php';
require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/models/model_commandes.php';

$produit = get_produit_by_id($produit_id);
if (!$produit) {
    header('Location: /');
    exit;
}

$quantite_vendue = get_quantite_vendue_produit($produit_id);
$stock_actuel = (int) ($produit['stock'] ?? 0);
$nombre_total = $stock_actuel + $quantite_vendue;
$stock_restant = $nombre_total - $quantite_vendue;

$prix_produit = (float) ($produit['prix'] ?? 0);
if (!empty($produit['prix_promotion']) && (float) $produit['prix_promotion'] < $prix_produit) {
    $prix_produit = (float) $produit['prix_promotion'];
}
$valeur_stock_actuel = $stock_actuel * $prix_produit;
$valeur_ventes = $quantite_vendue * $prix_produit;

$emplacement_vals = produit_emplacement_from_produit($produit);
$emplacement_resume = produit_emplacement_resume_court($emplacement_vals);

require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/produit_emplacement_entrepot.php';
$base = get_site_base_url();
if (file_exists(__DIR__ . '/includes/asset_version.php')) {
    require_once __DIR__ . '/includes/asset_version.php';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock - <?php echo htmlspecialchars($produit['nom']); ?> - FOUTA POIDS LOURDS</title>
    <link rel="stylesheet"
        href="/css/variables.css<?php echo function_exists('asset_version_query') ? asset_version_query() : ''; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-corps);
            background: transparent;
            min-height: 100vh;
            padding: 24px;
            color: var(--texte-fonce);
        }

        .container {
            max-width: 480px;
            margin: 0 auto;
        }

        .card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 2px solid #3564a6;
        }

        .card-header img {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
        }

        .card-header h1 {
            font-size: 18px;
            color: #1f2937;
            flex: 1;
        }

        .stock-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .stock-item {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        .stock-item.full {
            grid-column: 1 / -1;
        }

        .stock-item .label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .stock-item .value {
            font-size: 24px;
            font-weight: 700;
        }

        .stock-item.total .value {
            color: #1e40af;
        }

        .stock-item.vendu .value {
            color: #c2410c;
        }

        .stock-item.restant .value {
            color: #15803d;
        }

        .stock-item .detail {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 4px;
        }

        .emplacement-card {
            margin-top: 0;
        }

        .emplacement-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .emplacement-item {
            background: #f0f6fc;
            border-radius: 10px;
            padding: 12px;
            border: 1px solid rgba(53, 100, 166, 0.2);
            text-align: center;
        }

        .emplacement-item .label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 4px;
        }

        .emplacement-item .value {
            font-size: 15px;
            font-weight: 700;
            color: #3564a6;
        }

        .emplacement-resume {
            font-size: 13px;
            color: #475569;
            margin-bottom: 16px;
            line-height: 1.5;
        }

        .brand {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
            color: #64748b;
            font-weight: 600;
        }

        .brand a {
            color: #3564a6;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <img src="/upload/<?php echo htmlspecialchars($produit['image_principale'] ?? ''); ?>" alt=""
                    onerror="this.src='/image/produit1.jpg'">
                <h1><?php echo htmlspecialchars($produit['nom']); ?></h1>
            </div>

            <div class="stock-grid">
                <div class="stock-item total full">
                    <div class="label">Nombre total (initial + entrées)</div>
                    <div class="value"><?php echo $nombre_total; ?></div>
                    <div class="detail">Stock initial + entrées</div>
                </div>
                <div class="stock-item vendu">
                    <div class="label">Quantité vendue</div>
                    <div class="value"><?php echo $quantite_vendue; ?></div>
                </div>
                <div class="stock-item restant">
                    <div class="label">Stock restant</div>
                    <div class="value"><?php echo $stock_restant; ?></div>
                    <div class="detail">Total − Vendu</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="stock-item total" style="margin-bottom: 12px;">
                <div class="label">Valeur du stock actuel</div>
                <div class="value" style="font-size: 20px;">
                    <?php echo number_format($valeur_stock_actuel, 0, ',', ' '); ?> FCFA</div>
                <div class="detail"><?php echo $stock_actuel; ?> ×
                    <?php echo number_format($prix_produit, 0, ',', ' '); ?> FCFA</div>
            </div>
            <div class="stock-item vendu">
                <div class="label">Chiffre d'affaires (ventes)</div>
                <div class="value" style="font-size: 20px;"><?php echo number_format($valeur_ventes, 0, ',', ' '); ?>
                    FCFA</div>
                <div class="detail"><?php echo $quantite_vendue; ?> vendu(s)</div>
            </div>
        </div>

        <?php if (produit_emplacement_a_des_donnees($emplacement_vals)): ?>
        <div class="card emplacement-card">
            <h2 style="font-size: 16px; margin-bottom: 12px; color: #1f2937;">
                <i class="fas fa-map-pin" aria-hidden="true"></i> Emplacement entrepôt
            </h2>
            <?php if ($emplacement_resume !== ''): ?>
            <p class="emplacement-resume"><?php echo htmlspecialchars($emplacement_resume); ?></p>
            <?php endif; ?>
            <?php if (empty($emplacement_vals['chemin_libelle'])): ?>
            <div class="emplacement-list">
                <?php
                $etapes_stock = [
                    ['col' => 'etage', 'label' => 'Étage'],
                    ['col' => 'numero_rayon', 'label' => 'Rayon'],
                    ['col' => 'allee', 'label' => 'Allée'],
                    ['col' => 'zone_emplacement', 'label' => 'Zone'],
                    ['col' => 'position_emplacement', 'label' => 'Position'],
                    ['col' => 'barre_rayon', 'label' => 'Barre'],
                ];
                foreach ($etapes_stock as $etape):
                    $col = $etape['col'];
                    if (empty($emplacement_vals[$col])) {
                        continue;
                    }
                ?>
                <div class="emplacement-item">
                    <div class="label"><?php echo htmlspecialchars($etape['label']); ?></div>
                    <div class="value"><?php echo htmlspecialchars(produit_emplacement_option_label($col, $emplacement_vals[$col])); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <p class="brand">FOUTA POIDS LOURDS — Pièces poids lourds</p>
    </div>
</body>

</html>